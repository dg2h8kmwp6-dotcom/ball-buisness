<?php

namespace App\Console\Commands;

use App\Models\BallType;
use Database\Seeders\BallTypeSeeder;
use Illuminate\Console\Command;

class SyncBallTypes extends Command
{
    protected $signature = 'ballpit:sync-ball-types
                            {--overwrite : Reset existing ball types back to the built-in defaults}';

    protected $description = 'Add any missing default ball types to the catalogue';

    public function handle(): int
    {
        $defaults = BallTypeSeeder::defaults();
        $overwrite = (bool) $this->option('overwrite');

        if ($overwrite && ! $this->confirmOverwrite()) {
            $this->comment('Nothing changed.');

            return self::SUCCESS;
        }

        $created = 0;
        $updated = 0;

        foreach ($defaults as $slug => $attributes) {
            $ballType = BallType::where('slug', $slug)->first();

            if (! $ballType) {
                BallType::create($attributes);
                $created += 1;

                continue;
            }

            if ($overwrite) {
                // Leave any uploaded photo in place; only the catalogue
                // fields come from the defaults.
                $ballType->update($attributes);
                $updated += 1;
            }
        }

        $this->components->info(sprintf(
            '%d created, %d reset, %d left untouched.',
            $created,
            $updated,
            count($defaults) - $created - $updated,
        ));

        return self::SUCCESS;
    }

    protected function confirmOverwrite(): bool
    {
        return ! $this->input->isInteractive()
            || $this->confirm('Reset existing ball types to the built-in defaults? Edited prices and descriptions will be lost.');
    }
}
