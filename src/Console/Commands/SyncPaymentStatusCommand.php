<?php

namespace Cashkdiopen\Payments\Console\Commands;

use Cashkdiopen\Payments\Models\Payment;
use Cashkdiopen\Payments\Services\ProviderFactory;
use Illuminate\Console\Command;

class SyncPaymentStatusCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'cashkdiopen:sync-payment-status 
                            {--payment= : Specific payment ID to sync}
                            {--provider= : Sync payments for specific provider only}
                            {--limit=50 : Maximum number of payments to sync}';

    /**
     * The console command description.
     */
    protected $description = 'Sync payment status with payment providers';

    /**
     * Execute the console command.
     */
    public function handle(ProviderFactory $providerFactory): int
    {
        $paymentId = $this->option('payment');
        $providerName = $this->option('provider');
        $limit = (int) $this->option('limit');

        if ($paymentId) {
            return $this->syncSpecificPayment($paymentId, $providerFactory);
        }

        $query = Payment::where('status', 'pending')
            ->whereNotNull('provider_reference')
            ->latest();

        if ($providerName) {
            $query->where('provider', $providerName);
        }

        $payments = $query->limit($limit)->get();

        if ($payments->isEmpty()) {
            $this->info('No pending payments found to sync');
            return self::SUCCESS;
        }

        $this->info("Syncing {$payments->count()} pending payments...");
        
        $progressBar = $this->output->createProgressBar($payments->count());
        $updated = 0;
        $errors = 0;

        foreach ($payments as $payment) {
            try {
                $provider = $providerFactory->make($payment->provider);
                $status = $provider->getPaymentStatus($payment->provider_reference);
                
                if ($status['status'] !== $payment->status) {
                    $payment->update([
                        'status' => $status['status'],
                        'provider_data' => array_merge($payment->provider_data ?? [], $status),
                        'processed_at' => $status['status'] !== 'pending' ? now() : null,
                    ]);
                    $updated++;
                }
            } catch (\Exception $e) {
                $this->error("Failed to sync payment {$payment->id}: " . $e->getMessage());
                $errors++;
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->line('');
        $this->info("Sync completed: {$updated} payments updated, {$errors} errors");

        return self::SUCCESS;
    }

    /**
     * Sync a specific payment.
     */
    protected function syncSpecificPayment(string $paymentId, ProviderFactory $providerFactory): int
    {
        $payment = Payment::find($paymentId);
        
        if (!$payment) {
            $this->error("Payment not found: {$paymentId}");
            return self::FAILURE;
        }

        if (!$payment->provider_reference) {
            $this->error("Payment has no provider reference");
            return self::FAILURE;
        }

        try {
            $provider = $providerFactory->make($payment->provider);
            $status = $provider->getPaymentStatus($payment->provider_reference);
            
            $oldStatus = $payment->status;
            $payment->update([
                'status' => $status['status'],
                'provider_data' => array_merge($payment->provider_data ?? [], $status),
                'processed_at' => $status['status'] !== 'pending' ? now() : null,
            ]);

            $this->info("Payment {$payment->id} status updated from '{$oldStatus}' to '{$status['status']}'");
            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to sync payment: " . $e->getMessage());
            return self::FAILURE;
        }
    }
}