<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Renouvellement de votre abonnement Saliha Health</title>
</head>
<body style="margin:0;padding:0;background:#f4f6f8;font-family:Arial,Helvetica,sans-serif;color:#1f2933;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f8;padding:24px 12px;">
<tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:8px;border:1px solid #e4e7eb;">
<tr><td style="padding:24px 28px 8px;font-size:18px;font-weight:bold;color:#0f766e;">Saliha Health</td></tr>
<tr><td style="padding:8px 28px 0;font-size:14px;line-height:22px;">
<p style="margin:0 0 12px;">Bonjour,</p>
<p style="margin:0 0 16px;">Voici le lien de paiement pour le renouvellement de l'abonnement Saliha Health de <strong>{{ $structureName }}</strong>.</p>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;border:1px solid #e4e7eb;border-radius:6px;font-size:14px;">
<tr><td style="padding:10px 14px;color:#616e7c;">Formule</td><td style="padding:10px 14px;text-align:right;"><strong>{{ $planName }}</strong> ({{ $periodLabel }})</td></tr>
<tr><td style="padding:10px 14px;color:#616e7c;border-top:1px solid #e4e7eb;">Montant</td><td style="padding:10px 14px;text-align:right;border-top:1px solid #e4e7eb;"><strong>{{ $amount }}</strong></td></tr>
</table>
</td></tr>
<tr><td align="center" style="padding:24px 28px;">
<a href="{{ $paymentUrl }}" style="display:inline-block;background:#0f766e;color:#ffffff;text-decoration:none;font-weight:bold;font-size:15px;padding:12px 28px;border-radius:6px;">Payer {{ $amount }}</a>
</td></tr>
<tr><td style="padding:0 28px 8px;font-size:13px;line-height:20px;color:#616e7c;">
<p style="margin:0 0 12px;">Paiement sécurisé via DexPay (Wave ou Orange Money). Ce lien est à usage unique et expire le <strong>{{ $expiresAt }}</strong> (GMT). Passé ce délai, demandez un nouveau lien ou payez depuis l'écran « Mon abonnement » de votre espace.</p>
<p style="margin:0 0 12px;">Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :<br><a href="{{ $paymentUrl }}" style="color:#0f766e;word-break:break-all;">{{ $paymentUrl }}</a></p>
<p style="margin:0 0 20px;">La nouvelle période d'abonnement est activée automatiquement dès la confirmation du paiement.</p>
</td></tr>
<tr><td style="padding:16px 28px;border-top:1px solid #e4e7eb;font-size:12px;color:#9aa5b1;">Message envoyé par l'équipe Saliha Health. Si vous n'êtes pas à l'origine de cette demande, ignorez ce message.</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
