<?php

namespace App\Traits;

use App\Jobs\SendDonationPaidEmail;
use App\Models\EmailMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

trait DonationReceiptMailerTrait
{
    protected function queueDonationPaidEmail(
        array $payload,
        bool $isRecurring,
        string $intentId,
        string $moneySymbol,
        string $amountFormatted,
        int $eventTime,
        ?string $methodLabel = null,
        string $sourceLabel = 'Webhook'
    ): void {
        $sourcePrefix = trim($sourceLabel) ?: 'Webhook';
        $amountLabel = "{$moneySymbol}{$amountFormatted}";
        $donatedAtHuman = $eventTime ? date('M d, Y H:i', (int) $eventTime) : now()->format('M d, Y H:i');

        $toEmail = (string) ($payload['email'] ?? '');
        $isValidEmail = filter_var($toEmail, FILTER_VALIDATE_EMAIL);

        if (!$isValidEmail) {
            Log::warning("{$sourcePrefix} - invalid email, skipping receipt", [
                'external_id' => $payload['external_id'] ?? null,
                'email' => $toEmail ?: null,
                'intent_id' => $intentId ?: null,
            ]);
            return;
        }

        $alreadySent = EmailMessage::where('external_id', (string) ($payload['external_id'] ?? ''))
            ->where('to_email', $toEmail)
            ->exists();

        if ($alreadySent) {
            Log::info("{$sourcePrefix} - email already sent", [
                'external_id' => $payload['external_id'] ?? null,
                'to' => $toEmail,
            ]);
            return;
        }

        $token = Str::random(64);
        $links = [
            'site' => 'https://susanpetrescue.org/',
            'facebook' => 'https://www.facebook.com/susanpetrescue',
            'instagram' => 'https://www.instagram.com/susanpetrescue',
            'contact' => 'https://susanpetrescue.org/about-us',
        ];

        EmailMessage::create([
            'token' => $token,
            'external_id' => (string) ($payload['external_id'] ?? ''),
            'to_email' => $toEmail,
            'subject' => 'Thank you for your donation!',
            'sent_at' => now(),
            'links' => $links,
        ]);

        $donationAmount = $this->resolveDonationAmount($payload);
        $payerFirstName = $this->resolvePayerFirstName($payload);
        $dynamicMessage = $this->composeTieredThankYouMessage($donationAmount, $payerFirstName);

        $emailData = [
            'subject' => 'Thank you for your donation!',
            'human_now' => now()->format('M d, Y H:i'),
            'payer_name' => $payload['payer_name'] ?? 'friend',
            'email' => $toEmail,
            'amount_label' => $amountLabel,
            'external_id' => (string) ($payload['external_id'] ?? ''),
            'donation_id' => $intentId !== '' ? $intentId : (string) ($payload['external_id'] ?? ''),
            'donated_at' => $donatedAtHuman,
            'method' => $methodLabel ?? ($isRecurring ? 'stripe recurring' : 'stripe'),
            'track_token' => $token,
            'dynamic_message' => $dynamicMessage,
        ];

        SendDonationPaidEmail::dispatch($toEmail, $emailData);

        Log::info("{$sourcePrefix} - email queued", [
            'to' => $toEmail,
            'external_id' => $payload['external_id'] ?? null,
            'intent_id' => $intentId ?: null,
        ]);
    }

    protected function resolveDonationAmount(array $payload): float
    {
        if (isset($payload['amount']) && is_numeric($payload['amount'])) {
            return (float) $payload['amount'];
        }

        if (isset($payload['amount_cents']) && is_numeric($payload['amount_cents'])) {
            return (float) ($payload['amount_cents'] / 100);
        }

        return 0.0;
    }

    protected function composeTieredThankYouMessage(float $amount, string $firstName): string
    {
        $recipient = trim((string) $firstName) ?: 'friend';

        if ($amount >= 1000) {
            return <<<TEXT
            {$recipient}, thank you for your donation!

            {$recipient}, you represent the 0.1% of people who truly love animals and make this cause your life mission!
            People like you make us have more faith in life and believe in a world with less suffering and more love.
            Your generosity saves lives and provides shelter, comfort, protection, health, and food for dozens of animals.
            Thank you so much!!
            TEXT;
        }

        if ($amount >= 500) {
            return <<<TEXT
            {$recipient}, you represent the 1% of people who truly love animals and make this cause your life mission!
            People like you make us have more faith in life and believe in a world with less suffering and more love.
            Your generosity saves lives and provides shelter, comfort, protection, health, and food for dozens of animals.
            Thank you so much!!
            TEXT;
        }

        if ($amount >= 200) {
            return <<<TEXT
            {$recipient}, you represent the 10% of people who truly love animals and make this cause your life mission!
            Your generosity saves lives and provides shelter, comfort, protection, health, and food for dozens of animals.
            Thank you so much!!
            TEXT;
        }

        return <<<TEXT
        Your generosity saves lives and provides shelter, comfort, protection, health, and food for dozens of animals.
        Thank you so much!!

        Your donation provides:
        - Up to 44 pounds of dog food
        - Drinking water
        - Basic medications
        TEXT;
    }

    protected function resolvePayerFirstName(array $payload): string
    {
        $name = trim((string) ($payload['first_name'] ?? $payload['payer_name'] ?? ''));
        if ($name === '') {
            return 'friend';
        }

        $parts = preg_split('/\s+/', $name);
        return $parts[0] ?? $name;
    }
}
