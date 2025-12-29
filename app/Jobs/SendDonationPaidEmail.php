<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Mail\DonationPaidMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendDonationPaidEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 20;

    public function __construct(public string $to, public array $data) {}

    public function handle(): void
    {
        Mail::to($this->to)->send(new DonationPaidMail($this->data));
        Log::info('Donation paid email sent', ['to' => $this->to, 'external_id' => $this->data['external_id'] ?? null]);
    }

    public function failed(Throwable $e): void
    {
        Log::error('Donation paid email FAILED', [
            'to' => $this->to,
            'external_id' => $this->data['external_id'] ?? null,
            'error' => $e->getMessage(),
        ]);
    }
}
