<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Applies the hand-authored domain SQL in db/migrations/*.sql (R5) in numeric
 * order, tracking what has run in a `domain_migrations` table so it is safe to
 * run on every deploy (only new files are applied).
 *
 *   php artisan db:apply              apply any not-yet-applied SQL files
 *   php artisan db:apply --mark-only  record all current files as applied WITHOUT
 *                                     running them (use once on a database whose
 *                                     schema was already applied manually)
 */
class ApplyDbSql extends Command
{
    protected $signature = 'db:apply {--mark-only : Record files as applied without running them}';

    protected $description = 'Apply forward-only domain SQL from db/migrations/*.sql';

    public function handle(): int
    {
        DB::statement('CREATE TABLE IF NOT EXISTS domain_migrations (filename text PRIMARY KEY, applied_at timestamptz DEFAULT now())');

        $files = glob(base_path('db/migrations/*.sql'));
        sort($files);

        $applied = DB::table('domain_migrations')->pluck('filename')->all();
        $markOnly = (bool) $this->option('mark-only');
        $count = 0;

        foreach ($files as $path) {
            $name = basename($path);
            if (in_array($name, $applied, true)) {
                continue;
            }

            if (! $markOnly) {
                DB::unprepared(file_get_contents($path));
            }
            DB::table('domain_migrations')->insert(['filename' => $name, 'applied_at' => now()]);
            $this->line(($markOnly ? '  marked ' : '  applied ').$name);
            $count++;
        }

        $this->info($markOnly
            ? "Marked {$count} file(s) as applied."
            : "Applied {$count} file(s).");

        return self::SUCCESS;
    }
}
