<?php

namespace App\Console\Commands;

use App\Models\AttendanceWebhookLog;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class PurgeAttendanceWebhookLogs extends Command
{
    protected $signature = 'attendance:webhook-logs:purge
        {--heartbeat-days=30 : Retention in days for heartbeat/polling logs}
        {--log-days=90 : Retention in days for non-heartbeat logs}
        {--chunk=1000 : Number of rows deleted per batch}
        {--dry-run : Show how many rows would be deleted without deleting}';

    protected $description = 'Purge old attendance webhook logs based on retention policy.';

    private const CONNECTION_STATUSES = [
        'heartbeat',
        'command_poll',
        'device_command',
    ];

    public function handle(): int
    {
        $heartbeatDays = max(1, (int) $this->option('heartbeat-days'));
        $logDays = max($heartbeatDays, (int) $this->option('log-days'));
        $chunk = max(100, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');

        $heartbeatCutoff = now()->subDays($heartbeatDays);
        $logCutoff = now()->subDays($logDays);

        $heartbeatQuery = AttendanceWebhookLog::query()
            ->whereIn('status', self::CONNECTION_STATUSES)
            ->where('created_at', '<', $heartbeatCutoff);

        $otherQuery = AttendanceWebhookLog::query()
            ->whereNotIn('status', self::CONNECTION_STATUSES)
            ->where('created_at', '<', $logCutoff);

        $heartbeatCount = (clone $heartbeatQuery)->count();
        $otherCount = (clone $otherQuery)->count();

        $this->info(sprintf(
            'Retention: connection logs before %s (%d days), other logs before %s (%d days).',
            $heartbeatCutoff->format('Y-m-d H:i:s'),
            $heartbeatDays,
            $logCutoff->format('Y-m-d H:i:s'),
            $logDays
        ));

        if ($dryRun) {
            $this->line("Dry run: {$heartbeatCount} connection logs and {$otherCount} other logs would be deleted.");
            return self::SUCCESS;
        }

        $deletedHeartbeat = $this->deleteInChunks($heartbeatCutoff, true, $chunk);
        $deletedOther = $this->deleteInChunks($logCutoff, false, $chunk);

        $this->info("Deleted {$deletedHeartbeat} connection logs and {$deletedOther} other logs.");

        return self::SUCCESS;
    }

    private function deleteInChunks(Carbon $cutoff, bool $connectionLogs, int $chunk): int
    {
        $deleted = 0;

        do {
            $ids = AttendanceWebhookLog::query()
                ->when(
                    $connectionLogs,
                    fn ($query) => $query->whereIn('status', self::CONNECTION_STATUSES),
                    fn ($query) => $query->whereNotIn('status', self::CONNECTION_STATUSES)
                )
                ->where('created_at', '<', $cutoff)
                ->orderBy('id')
                ->limit($chunk)
                ->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $deleted += AttendanceWebhookLog::query()
                ->whereIn('id', $ids)
                ->delete();
        } while ($ids->count() === $chunk);

        return $deleted;
    }
}
