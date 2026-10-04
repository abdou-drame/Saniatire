<?php

namespace Tests\Feature;

use App\Domain\Platform\Mail\SubscriptionPaymentLinkMail;
use App\Domain\Platform\Models\PaymentTransaction;
use App\Domain\Platform\Models\Plan;
use App\Domain\Platform\Models\PlatformAdmin;
use App\Domain\Platform\Models\Subscription;
use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

/**
 * Paiement DexPay des abonnements : création de session (plateforme et
 * self-service), webhook signé, idempotence. L'API DexPay est simulée
 * (Http::fake) ; les clés sont des valeurs de test posées ici, jamais
 * celles du compte réel.
 */
class DexPayPaymentTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'sk_test_secret_de_test';

    private Structure $structure;

    private ?string $platformToken = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->travelTo(Carbon::parse('2026-10-15 10:00:00'));

        config([
            'services.dexpay.public_key' => 'pk_test_cle_de_test',
            'services.dexpay.secret_key' => self::SECRET,
            'services.dexpay.base_url' => 'https://api-sandbox.dexpay.africa/api/v1',
            'services.dexpay.return_url' => 'https://app.example.test',
            'services.dexpay.webhook_url' => 'https://api.example.test/api/webhooks/dexpay',
        ]);

        $this->structure = Structure::factory()->create();
    }

    private function fakeDexPay(?callable $assertBeforeResponse = null): void
    {
        Http::fake([
            'api-sandbox.dexpay.africa/*' => function (HttpRequest $request) use ($assertBeforeResponse) {
                if ($assertBeforeResponse) {
                    $assertBeforeResponse($request);
                }

                return Http::response([
                    'status' => 'success',
                    'message' => 'Checkout session created',
                    'data' => [
                        'reference' => $request['reference'],
                        'amount' => $request['amount'],
                        'currency' => 'XOF',
                        'payment_url' => 'https://pay.dexpay.africa/checkout/'.$request['reference'],
                        'status' => 'pending',
                        'isSandbox' => true,
                    ],
                ], 201);
            },
        ]);
    }

    private function plan(string $code = 'pro'): Plan
    {
        return Plan::where('code', $code)->firstOrFail();
    }

    private function period(Structure $structure, string $startsAt, string $endsAt, string $plan = 'pro', string $status = 'active'): Subscription
    {
        return Subscription::create([
            'structure_id' => $structure->id,
            'plan_id' => $this->plan($plan)->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => $status,
        ]);
    }

    private function staffToken(Structure $structure, string $role = 'administrateur'): string
    {
        // 2FA activée : obligatoire pour certains rôles (direction), sans
        // rapport avec le paiement.
        $user = User::factory()->for($structure)->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole($role);

        return $user->createToken('api')->plainTextToken;
    }

    private function staff(string $token, string $method, string $uri, array $data = []): TestResponse
    {
        Auth::forgetGuards();

        return $this->withHeader('Authorization', "Bearer {$token}")->json($method, $uri, $data);
    }

    private function platform(string $method, string $uri, array $data = []): TestResponse
    {
        if ($this->platformToken === null) {
            PlatformAdmin::create([
                'name' => 'Admin Plateforme',
                'email' => 'platform-admin@example.test',
                'password' => Hash::make('un-mot-de-passe-solide'),
            ]);

            Auth::forgetGuards();
            $this->platformToken = $this->postJson('/api/platform/login', [
                'email' => 'platform-admin@example.test',
                'password' => 'un-mot-de-passe-solide',
            ])->assertOk()->json('token');
        }

        Auth::forgetGuards();

        return $this->withHeader('Authorization', "Bearer {$this->platformToken}")->json($method, $uri, $data);
    }

    private function pendingTransaction(string $period = 'monthly', int $amount = 35000): PaymentTransaction
    {
        return PaymentTransaction::create([
            'structure_id' => $this->structure->id,
            'plan_id' => $this->plan()->id,
            'period' => $period,
            'reference' => 'SUB-'.$this->structure->id.'-TEST',
            'amount' => $amount,
            'currency' => 'XOF',
            'status' => PaymentTransaction::STATUS_PENDING,
            'provider' => 'dexpay',
            'initiated_by' => 'platform_admin:1',
        ]);
    }

    private function webhook(array $payload, ?string $signature = null, bool $sign = true): TestResponse
    {
        $body = json_encode($payload);
        $headers = ['Content-Type' => 'application/json', 'Accept' => 'application/json'];
        if ($sign) {
            $headers['X-Webhook-Signature'] = $signature ?? hash_hmac('sha256', $body, self::SECRET);
        }

        Auth::forgetGuards();

        return $this->call('POST', '/api/webhooks/dexpay', [], [], [], $this->transformHeadersToServerVars($headers), $body);
    }

    private function event(string $event, PaymentTransaction $transaction, array $overrides = []): array
    {
        return [
            'event' => $event,
            'data' => [
                'reference' => $transaction->reference,
                'checkout_session_id' => 'cs_123',
                'transaction_id' => 'tx_456',
                'amount' => $transaction->amount,
                'currency' => 'XOF',
                'status' => 'completed',
                'operator' => 'wave',
                'customer' => ['phone' => '+221770000000'],
                ...$overrides,
            ],
            'timestamp' => '2026-10-15T10:05:00Z',
        ];
    }

    // --- Création de session par la plateforme -------------------------------

    public function test_platform_checkout_records_a_pending_transaction_before_calling_dexpay(): void
    {
        $this->fakeDexPay(function () {
            // Au moment où DexPay est appelé, la ligne existe déjà.
            $this->assertSame(1, PaymentTransaction::where('status', PaymentTransaction::STATUS_PENDING)->count());
        });

        $response = $this->platform('POST', "/api/platform/structures/{$this->structure->id}/subscriptions/dexpay-checkout", [
            'plan_id' => $this->plan()->id,
            'period' => 'monthly',
        ])->assertCreated();

        $transaction = PaymentTransaction::sole();
        $this->assertSame(PaymentTransaction::STATUS_PENDING, $transaction->status);
        $this->assertStringStartsWith('platform_admin:', $transaction->initiated_by);
        $this->assertSame("https://pay.dexpay.africa/checkout/{$transaction->reference}", $response->json('data.payment_url'));
        $this->assertSame('platform_admin', $response->json('data.origin'));
        $this->assertArrayNotHasKey('raw_payload', $response->json('data'));

        $this->assertTrue(Activity::where('log_name', 'administration_plateforme')
            ->where('structure_id', $this->structure->id)
            ->where('properties->action', 'creation_session_paiement_dexpay')
            ->exists());
    }

    public function test_the_amount_sent_to_dexpay_is_the_plan_price_and_never_a_client_value(): void
    {
        $this->fakeDexPay();

        $this->platform('POST', "/api/platform/structures/{$this->structure->id}/subscriptions/dexpay-checkout", [
            'plan_id' => $this->plan('business')->id,
            'period' => 'annual',
            'amount' => 1, // ignoré
        ])->assertCreated();

        Http::assertSent(function (HttpRequest $request) {
            return $request->url() === 'https://api-sandbox.dexpay.africa/api/v1/checkout-sessions'
                && $request->hasHeader('x-api-key', 'pk_test_cle_de_test')
                && $request['amount'] === 750000
                && $request['currency'] === 'XOF'
                && $request['is_one_shot_payment'] === true
                && $request['webhook_url'] === 'https://api.example.test/api/webhooks/dexpay'
                && $request['success_url'] === 'https://app.example.test/mon-abonnement?paiement=succes';
        });
        $this->assertSame(750000, PaymentTransaction::sole()->amount);
    }

    public function test_a_plan_on_quote_cannot_be_paid_online(): void
    {
        $this->fakeDexPay();
        $enterprise = $this->plan('enterprise');

        $this->platform('POST', "/api/platform/structures/{$this->structure->id}/subscriptions/dexpay-checkout", [
            'plan_id' => $enterprise->id,
            'period' => 'monthly',
        ])->assertStatus(422);

        Http::assertNothingSent();
        $this->assertSame(0, PaymentTransaction::count());
    }

    public function test_a_dexpay_error_marks_the_transaction_failed_and_returns_502(): void
    {
        Http::fake(['api-sandbox.dexpay.africa/*' => Http::response(['status' => 'error', 'message' => 'Invalid API key'], 401)]);

        $this->platform('POST', "/api/platform/structures/{$this->structure->id}/subscriptions/dexpay-checkout", [
            'plan_id' => $this->plan()->id,
            'period' => 'monthly',
        ])->assertStatus(502);

        // La ligne créée avant l'appel subsiste, en échec, avec la réponse.
        $transaction = PaymentTransaction::sole();
        $this->assertSame(PaymentTransaction::STATUS_FAILED, $transaction->status);
        $this->assertArrayHasKey('erreur_creation', $transaction->raw_payload);
    }

    public function test_platform_lists_only_the_transactions_of_the_requested_structure(): void
    {
        $this->pendingTransaction();
        $other = Structure::factory()->create();
        PaymentTransaction::create([
            'structure_id' => $other->id, 'plan_id' => $this->plan()->id, 'period' => 'monthly',
            'reference' => 'SUB-OTHER', 'amount' => 35000, 'currency' => 'XOF',
            'status' => 'en_attente', 'provider' => 'dexpay', 'initiated_by' => 'platform_admin:1',
        ]);

        $this->platform('GET', "/api/platform/structures/{$this->structure->id}/payment-transactions")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.reference', 'SUB-'.$this->structure->id.'-TEST');
    }

    // --- Webhook -------------------------------------------------------------

    public function test_an_invalid_or_missing_signature_is_rejected_with_401(): void
    {
        $transaction = $this->pendingTransaction();
        $payload = $this->event('checkout.completed', $transaction);

        $this->webhook($payload, 'deadbeef')->assertStatus(401);
        $this->webhook($payload, sign: false)->assertStatus(401);
        // Signé avec une autre clé.
        $this->webhook($payload, hash_hmac('sha256', json_encode($payload), 'sk_test_autre'))->assertStatus(401);

        $this->assertSame(0, Subscription::count());
        $this->assertSame(PaymentTransaction::STATUS_PENDING, $transaction->fresh()->status);
    }

    public function test_a_completed_payment_creates_the_subscription_period_automatically(): void
    {
        $transaction = $this->pendingTransaction();

        $this->webhook($this->event('checkout.completed', $transaction))->assertOk()->assertJson(['received' => true]);

        $subscription = Subscription::sole();
        $this->assertSame('active', $subscription->status);
        $this->assertSame('monthly', $subscription->billing_period);
        $this->assertNull($subscription->created_by);
        $this->assertSame('2026-10-15', $subscription->starts_at->toDateString());
        $this->assertSame('2026-11-14', $subscription->ends_at->toDateString());

        $transaction->refresh();
        $this->assertSame(PaymentTransaction::STATUS_COMPLETED, $transaction->status);
        $this->assertSame($subscription->id, $transaction->subscription_id);
        $this->assertSame('tx_456', $transaction->transaction_id);
        $this->assertSame('cs_123', $transaction->checkout_session_id);

        $audit = Activity::where('log_name', 'administration_plateforme')
            ->where('properties->action', 'renouvellement_paiement_dexpay')
            ->sole();
        $this->assertSame($this->structure->id, $audit->structure_id);
        $this->assertNull($audit->causer_id);
        $this->assertTrue($audit->properties['automatique']);
    }

    public function test_the_new_period_follows_a_period_still_running(): void
    {
        $this->period($this->structure, '2026-01-01', '2026-12-31');
        $transaction = $this->pendingTransaction('annual', 350000);

        $this->webhook($this->event('checkout.completed', $transaction))->assertOk();

        $new = Subscription::where('billing_period', 'annual')->sole();
        $this->assertSame('2027-01-01', $new->starts_at->toDateString());
        $this->assertSame('2027-12-31', $new->ends_at->toDateString());
    }

    public function test_a_replayed_completed_event_creates_a_single_period(): void
    {
        $transaction = $this->pendingTransaction();
        $payload = $this->event('checkout.completed', $transaction);

        $this->webhook($payload)->assertOk();
        $this->webhook($payload)->assertOk();
        $this->webhook($payload)->assertOk();

        $this->assertSame(1, Subscription::count());
        $this->assertSame(1, Activity::where('properties->action', 'renouvellement_paiement_dexpay')->count());
    }

    public function test_a_flat_payload_is_accepted_too(): void
    {
        $transaction = $this->pendingTransaction();
        $event = $this->event('checkout.completed', $transaction);

        $this->webhook(['event' => $event['event'], ...$event['data']])->assertOk();

        $this->assertSame(1, Subscription::count());
    }

    public function test_a_failed_payment_creates_nothing(): void
    {
        $transaction = $this->pendingTransaction();

        $this->webhook($this->event('checkout.failed', $transaction, ['status' => 'failed', 'failure_reason' => 'Solde insuffisant']))->assertOk();

        $this->assertSame(0, Subscription::count());
        $this->assertSame(PaymentTransaction::STATUS_FAILED, $transaction->fresh()->status);
        $this->assertTrue(Activity::where('properties->action', 'paiement_dexpay_echoue')->exists());
    }

    public function test_a_cancelled_payment_creates_nothing(): void
    {
        $transaction = $this->pendingTransaction();

        $this->webhook($this->event('checkout.cancelled', $transaction, ['status' => 'cancelled']))->assertOk();

        $this->assertSame(0, Subscription::count());
        $this->assertSame(PaymentTransaction::STATUS_CANCELLED, $transaction->fresh()->status);
    }

    public function test_a_late_failure_never_undoes_a_confirmed_payment(): void
    {
        $transaction = $this->pendingTransaction();

        $this->webhook($this->event('checkout.completed', $transaction))->assertOk();
        $this->webhook($this->event('checkout.failed', $transaction, ['status' => 'failed']))->assertOk();

        $this->assertSame(PaymentTransaction::STATUS_COMPLETED, $transaction->fresh()->status);
        $this->assertSame(1, Subscription::count());
    }

    public function test_an_amount_mismatch_opens_no_period(): void
    {
        $transaction = $this->pendingTransaction();

        $this->webhook($this->event('checkout.completed', $transaction, ['amount' => 100]))->assertOk();

        $this->assertSame(0, Subscription::count());
        $this->assertSame(PaymentTransaction::STATUS_PENDING, $transaction->fresh()->status);
    }

    public function test_initiated_unknown_events_and_unknown_references_do_not_fail(): void
    {
        $transaction = $this->pendingTransaction();

        $this->webhook($this->event('checkout.initiated', $transaction, ['status' => 'initiated']))->assertOk();
        $this->assertSame(PaymentTransaction::STATUS_PENDING, $transaction->fresh()->status);
        $this->assertSame('cs_123', $transaction->fresh()->checkout_session_id);

        $this->webhook(['event' => 'payout.completed', 'data' => ['reference' => 'PAYOUT-1']])->assertOk();
        $this->webhook(['event' => 'checkout.completed', 'data' => ['reference' => 'INCONNUE', 'amount' => 1, 'currency' => 'XOF']])->assertOk();

        $this->assertSame(0, Subscription::count());
    }

    public function test_an_invalid_json_body_is_rejected(): void
    {
        $body = 'pas du json';
        Auth::forgetGuards();

        $this->call('POST', '/api/webhooks/dexpay', [], [], [], $this->transformHeadersToServerVars([
            'X-Webhook-Signature' => hash_hmac('sha256', $body, self::SECRET),
            'Accept' => 'application/json',
        ]), $body)->assertStatus(400);
    }

    // --- Self-service structure ----------------------------------------------

    public function test_a_structure_admin_pays_for_its_own_structure_with_its_last_plan_and_period(): void
    {
        $this->fakeDexPay();
        $this->period($this->structure, '2025-10-01', '2026-09-30', 'business'); // annuelle, expirée (grâce)
        $token = $this->staffToken($this->structure, 'direction');

        $response = $this->staff($token, 'POST', '/api/subscription/dexpay-checkout', [
            'structure_id' => 999, 'plan_id' => 1, 'amount' => 1, // ignorés
        ])->assertCreated();

        $transaction = PaymentTransaction::sole();
        $this->assertSame($this->structure->id, $transaction->structure_id);
        $this->assertSame($this->plan('business')->id, $transaction->plan_id);
        $this->assertSame('annual', $transaction->period);
        $this->assertSame(750000, $transaction->amount);
        $this->assertStringStartsWith('structure_admin:', $transaction->initiated_by);
        $this->assertSame('structure_admin', $response->json('data.origin'));
        $this->assertNotEmpty($response->json('data.payment_url'));
    }

    public function test_self_service_only_ever_pays_for_the_users_own_structure(): void
    {
        $this->fakeDexPay();
        $structureB = Structure::factory()->create();
        $this->period($this->structure, '2026-09-01', '2026-09-30', 'pro');
        $this->period($structureB, '2026-09-01', '2026-09-30', 'premium');

        $tokenA = $this->staffToken($this->structure);
        $this->staff($tokenA, 'POST', '/api/subscription/dexpay-checkout', ['structure_id' => $structureB->id])->assertCreated();

        $this->assertSame(0, PaymentTransaction::where('structure_id', $structureB->id)->count());
        $this->assertSame(35000, PaymentTransaction::where('structure_id', $this->structure->id)->sole()->amount);

        // Et un compte de A n'a aucun accès à la route plateforme de B.
        $this->staff($tokenA, 'POST', "/api/platform/structures/{$structureB->id}/subscriptions/dexpay-checkout", [
            'plan_id' => $this->plan()->id, 'period' => 'monthly',
        ])->assertUnauthorized();
        $this->staff($tokenA, 'GET', "/api/platform/structures/{$structureB->id}/payment-transactions")->assertUnauthorized();
    }

    public function test_a_non_admin_role_cannot_pay(): void
    {
        $this->fakeDexPay();
        $this->period($this->structure, '2026-09-01', '2026-09-30');
        $token = $this->staffToken($this->structure, 'medecin');

        $this->staff($token, 'POST', '/api/subscription/dexpay-checkout')->assertForbidden();

        Http::assertNothingSent();
        $this->assertSame(0, PaymentTransaction::count());
    }

    public function test_self_service_without_any_period_gives_a_clear_error(): void
    {
        $this->fakeDexPay();
        $token = $this->staffToken($this->structure);

        $response = $this->staff($token, 'POST', '/api/subscription/dexpay-checkout')->assertStatus(422);

        $this->assertStringContainsString('Aucun abonnement', $response->json('message'));
        Http::assertNothingSent();
    }

    public function test_self_service_is_refused_for_a_suspended_subscription(): void
    {
        $this->fakeDexPay();
        $this->period($this->structure, '2026-10-01', '2026-10-31', 'pro', 'suspendue');
        $token = $this->staffToken($this->structure);

        $this->staff($token, 'POST', '/api/subscription/dexpay-checkout')->assertStatus(422);
        Http::assertNothingSent();
    }

    public function test_self_service_remains_possible_in_read_only_mode(): void
    {
        $this->fakeDexPay();
        $this->period($this->structure, '2026-08-01', '2026-08-31'); // grâce finie le 07/09
        $token = $this->staffToken($this->structure);

        $me = $this->staff($token, 'GET', '/api/auth/me')->assertOk();
        $this->assertTrue($me->json('data.subscription.read_only') ?? $me->json('subscription.read_only'));
        $this->assertTrue($me->json('data.subscription.can_pay_online') ?? $me->json('subscription.can_pay_online'));

        $this->staff($token, 'POST', '/api/subscription/dexpay-checkout')->assertCreated();
    }

    // --- Écran « Mon abonnement » --------------------------------------------

    public function test_my_subscription_shows_terms_and_allows_paying_in_advance(): void
    {
        $this->fakeDexPay();
        // Période en cours, loin de l'échéance : aucune bannière, mais
        // l'écran reste consultable et le paiement anticipé possible.
        $this->period($this->structure, '2026-01-01', '2026-12-31', 'pro');
        $token = $this->staffToken($this->structure);

        $this->staff($token, 'GET', '/api/subscription')
            ->assertOk()
            ->assertJsonPath('data.state', 'essai_ou_actif')
            ->assertJsonPath('data.current.plan_name', $this->plan()->name)
            ->assertJsonPath('data.current.billing_period', 'annual')
            ->assertJsonPath('data.current.ends_at', '2026-12-31')
            ->assertJsonPath('data.upcoming', null)
            ->assertJsonPath('data.renewal.amount', 350000)
            ->assertJsonPath('data.renewal.starts_at', '2027-01-01')
            ->assertJsonPath('data.renewal.ends_at', '2027-12-31')
            ->assertJsonPath('data.can_pay_online', true)
            ->assertJsonPath('data.payments', []);

        $this->staff($token, 'POST', '/api/subscription/dexpay-checkout')->assertCreated();
        $transaction = PaymentTransaction::sole();
        $this->webhook($this->event('checkout.completed', $transaction))->assertOk();

        // La période payée d'avance apparaît comme « à venir », le
        // renouvellement suivant s'enchaîne après elle.
        $this->staff($token, 'GET', '/api/subscription')
            ->assertOk()
            ->assertJsonPath('data.current.ends_at', '2026-12-31')
            ->assertJsonPath('data.upcoming.starts_at', '2027-01-01')
            ->assertJsonPath('data.renewal.starts_at', '2028-01-01')
            ->assertJsonPath('data.payments.0.status', 'complete')
            ->assertJsonMissingPath('data.payments.0.raw_payload');
    }

    public function test_my_subscription_explains_why_online_payment_is_unavailable(): void
    {
        $this->period($this->structure, '2026-01-01', '2026-12-31', 'enterprise');
        $token = $this->staffToken($this->structure);

        $response = $this->staff($token, 'GET', '/api/subscription')
            ->assertOk()
            ->assertJsonPath('data.renewal', null)
            ->assertJsonPath('data.can_pay_online', false);
        $this->assertStringContainsString('pas de tarif en ligne', $response->json('data.unavailable_reason'));
    }

    public function test_my_subscription_is_reserved_to_admin_roles_and_own_structure(): void
    {
        $structureB = Structure::factory()->create();
        $this->period($this->structure, '2026-01-01', '2026-12-31', 'pro');
        $this->period($structureB, '2026-01-01', '2026-12-31', 'premium');

        $this->staff($this->staffToken($this->structure, 'medecin'), 'GET', '/api/subscription')->assertForbidden();
        $this->staff($this->staffToken($this->structure, 'direction'), 'GET', '/api/subscription')
            ->assertOk()
            ->assertJsonPath('data.current.plan_name', $this->plan()->name);
    }

    public function test_can_pay_online_is_false_for_other_roles_and_plans_on_quote(): void
    {
        $this->period($this->structure, '2026-08-01', '2026-08-31', 'enterprise');
        $admin = $this->staffToken($this->structure);
        $doctor = $this->staffToken($this->structure, 'medecin');

        $adminMe = $this->staff($admin, 'GET', '/api/auth/me')->assertOk();
        $this->assertFalse($adminMe->json('data.subscription.can_pay_online') ?? $adminMe->json('subscription.can_pay_online'));

        $doctorMe = $this->staff($doctor, 'GET', '/api/auth/me')->assertOk();
        $this->assertFalse($doctorMe->json('data.subscription.can_pay_online') ?? $doctorMe->json('subscription.can_pay_online'));
    }

    private function linkTransaction(): PaymentTransaction
    {
        $transaction = $this->pendingTransaction();
        $transaction->update(['payment_url' => 'https://pay.dexpay.africa/checkout/'.$transaction->reference]);

        return $transaction;
    }

    public function test_platform_admin_emails_the_payment_link_to_the_structure_address(): void
    {
        Mail::fake();
        $this->structure->update(['email' => 'direction@clinique-test.sn', 'legal_name' => 'Clinique Test Dakar', 'trade_name' => null]);
        $transaction = $this->linkTransaction();

        $this->platform('POST', "/api/platform/structures/{$this->structure->id}/payment-transactions/{$transaction->id}/send-email")
            ->assertOk()
            ->assertJsonPath('data.sent_to', 'direction@clinique-test.sn');

        Mail::assertSent(SubscriptionPaymentLinkMail::class, function (SubscriptionPaymentLinkMail $mail) use ($transaction) {
            $html = $mail->render();

            return $mail->hasTo('direction@clinique-test.sn')
                && $mail->hasSubject('Renouvellement de votre abonnement Saliha Health')
                && str_contains($html, $transaction->payment_url)
                && str_contains($html, 'Clinique Test Dakar')
                && str_contains($html, '35 000 FCFA')
                && str_contains($html, $this->plan()->name)
                && str_contains($html, 'usage unique')
                && str_contains($html, '16/10/2026 à 10:00');
        });
        Mail::assertSentCount(1);

        $activity = Activity::where('log_name', 'administration_plateforme')
            ->where('properties->action', 'envoi_lien_paiement_dexpay_email')
            ->sole();
        $this->assertSame($this->structure->id, (int) $activity->structure_id);
        $this->assertSame('direction@clinique-test.sn', $activity->properties['destinataire']);
        $this->assertSame($transaction->reference, $activity->properties['reference']);
        $this->assertNotNull($activity->causer_id);
    }

    public function test_smtp_failure_returns_a_clear_error_and_is_not_audited(): void
    {
        $this->structure->update(['email' => 'direction@clinique-test.sn']);
        $transaction = $this->linkTransaction();
        $this->platform('GET', "/api/platform/structures/{$this->structure->id}/payment-transactions")->assertOk();

        Mail::shouldReceive('to')->andThrow(new TransportException('Connection could not be established with host smtp'));

        $this->platform('POST', "/api/platform/structures/{$this->structure->id}/payment-transactions/{$transaction->id}/send-email")
            ->assertStatus(503)
            ->assertJsonPath('message', "L'email n'a pas pu être envoyé (serveur d'envoi indisponible). Le lien n'a pas été transmis : réessayez plus tard ou copiez-le manuellement.");

        $this->assertFalse(Activity::where('properties->action', 'envoi_lien_paiement_dexpay_email')->exists());
    }

    public function test_payment_link_email_is_refused_when_not_sendable(): void
    {
        Mail::fake();
        $transaction = $this->linkTransaction();
        $uri = "/api/platform/structures/{$this->structure->id}/payment-transactions/{$transaction->id}/send-email";

        // Pas d'adresse enregistrée.
        $this->structure->update(['email' => null]);
        $this->platform('POST', $uri)->assertStatus(422);

        // Transaction d'une autre structure.
        $other = Structure::factory()->create();
        $this->platform('POST', "/api/platform/structures/{$other->id}/payment-transactions/{$transaction->id}/send-email")->assertNotFound();

        // Lien expiré (au-delà des 24 h DexPay).
        $this->structure->update(['email' => 'direction@clinique-test.sn']);
        $this->travel(25)->hours();
        // Le jeton plateforme expire après 12 h (sanctum.expiration) : on se reconnecte.
        Auth::forgetGuards();
        $this->platformToken = $this->postJson('/api/platform/login', [
            'email' => 'platform-admin@example.test',
            'password' => 'un-mot-de-passe-solide',
        ])->assertOk()->json('token');
        $this->platform('POST', $uri)->assertStatus(422)->assertJsonPath('message', "Ce lien de paiement a expiré : générez-en un nouveau avant de l'envoyer.");
        $this->travelBack();
        $this->travelTo(Carbon::parse('2026-10-15 11:00:00'));

        // Paiement déjà traité.
        $transaction->update(['status' => PaymentTransaction::STATUS_COMPLETED]);
        $this->platform('POST', $uri)->assertStatus(422);

        Mail::assertNothingSent();
    }
}
