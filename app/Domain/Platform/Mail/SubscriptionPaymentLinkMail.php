<?php

namespace App\Domain\Platform\Mail;

use App\Domain\Platform\Models\PaymentTransaction;
use App\Domain\Structure\Models\Structure;
use Carbon\CarbonInterface;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Lien de paiement DexPay transmis à la structure par l'administration
 * plateforme. Envoyé de façon synchrone (pas de ShouldQueue) : l'échec SMTP
 * doit remonter à l'écran plutôt que d'être avalé par une file.
 */
class SubscriptionPaymentLinkMail extends Mailable
{
    /** Durée de validité d'une session DexPay sans expires_at explicite. */
    public const LINK_VALIDITY_HOURS = 24;

    public function __construct(
        public Structure $structure,
        public PaymentTransaction $transaction,
    ) {}

    public static function expiresAt(PaymentTransaction $transaction): CarbonInterface
    {
        return $transaction->created_at->copy()->addHours(self::LINK_VALIDITY_HOURS);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Renouvellement de votre abonnement Saliha Health');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.subscription-payment-link',
            with: [
                'structureName' => $this->structure->trade_name ?: $this->structure->legal_name,
                'planName' => $this->transaction->plan?->name ?? 'Saliha Health',
                'periodLabel' => $this->transaction->period === 'annual' ? 'annuel' : 'mensuel',
                'amount' => number_format($this->transaction->amount, 0, ',', ' ').' FCFA',
                'paymentUrl' => $this->transaction->payment_url,
                'expiresAt' => self::expiresAt($this->transaction)->timezone(config('app.timezone'))->format('d/m/Y à H:i'),
            ],
        );
    }
}
