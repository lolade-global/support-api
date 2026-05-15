<?php

namespace App\Console\Commands;

use App\Models\Conversation;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Auto-closes conversations that have had no activity for N days. Meant to
 * run on a schedule (see routes/console.php). Chunks the update so it stays
 * memory-safe even when there are millions of rows to consider.
 */
class CloseStaleConversations extends Command
{
    protected $signature = 'conversations:close-stale
                            {--days=14 : Days of inactivity before auto-closing}
                            {--dry-run : Report what would change without writing}';

    protected $description = 'Close conversations with no activity beyond the inactivity threshold';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = Carbon::now()->subDays($days);
        $dryRun = (bool) $this->option('dry-run');

        $query = Conversation::query()
            ->where('status', '!=', Conversation::STATUS_CLOSED)
            ->where('last_message_at', '<', $cutoff);

        $count = $query->count();

        if ($count === 0) {
            $this->info('No stale conversations found.');
            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn("[dry-run] Would close {$count} conversation(s) idle since {$cutoff->toDateString()}.");
            return self::SUCCESS;
        }

        // Chunked update keeps a single query from locking a huge range /
        // blowing up memory on a large table.
        $closed = 0;
        $query->clone()->chunkById(1000, function ($conversations) use (&$closed) {
            $ids = $conversations->pluck('id')->all();
            Conversation::whereIn('id', $ids)->update(['status' => Conversation::STATUS_CLOSED]);
            $closed += count($ids);
        });

        $this->info("Closed {$closed} stale conversation(s).");

        return self::SUCCESS;
    }
}
