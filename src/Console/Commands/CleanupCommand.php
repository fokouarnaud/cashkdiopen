<?php

namespace Cashkdiopen\Payments\Console\Commands;

use Cashkdiopen\Payments\Models\Payment;
use Cashkdiopen\Payments\Models\WebhookLog;
use Illuminate\Console\Command;

class CleanupCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'cashkdiopen:cleanup 
                            {--days=30 : Number of days to keep data}
                            {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     */
    protected $description = 'Clean up old Cashkdiopen data';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');
        
        $cutoffDate = now()->subDays($days);

        $this->info("Cleaning up data older than {$days} days (before {$cutoffDate->format('Y-m-d H:i:s')})");
        
        if ($dryRun) {
            $this->warn('DRY RUN MODE - No data will actually be deleted');
        }

        // Clean up old completed payments
        $completedPaymentsCount = Payment::where('status', 'completed')
            ->where('updated_at', '<', $cutoffDate)
            ->count();

        if ($completedPaymentsCount > 0) {
            $this->line("Found {$completedPaymentsCount} completed payments to clean up");
            
            if (!$dryRun) {
                Payment::where('status', 'completed')
                    ->where('updated_at', '<', $cutoffDate)
                    ->delete();
                $this->info("Deleted {$completedPaymentsCount} completed payments");
            }
        }

        // Clean up old webhook logs
        $webhookLogsCount = WebhookLog::where('status', 'processed')
            ->where('created_at', '<', $cutoffDate)
            ->count();

        if ($webhookLogsCount > 0) {
            $this->line("Found {$webhookLogsCount} processed webhook logs to clean up");
            
            if (!$dryRun) {
                WebhookLog::where('status', 'processed')
                    ->where('created_at', '<', $cutoffDate)
                    ->delete();
                $this->info("Deleted {$webhookLogsCount} processed webhook logs");
            }
        }

        if ($completedPaymentsCount === 0 && $webhookLogsCount === 0) {
            $this->info('No old data found to clean up');
        }

        return self::SUCCESS;
    }
}