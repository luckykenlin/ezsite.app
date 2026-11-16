<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Templates\SiteTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

final class DatabaseSeeder extends Seeder
{
    /**
     * Model events must stay ON here: a `Tenant`'s UUID primary key and its
     * `data` column are both filled by `creating`/`saving` listeners, so
     * `WithoutModelEvents` makes every demo site insert a null id.
     */
    public function run(): void
    {
        User::factory()->superAdmin()->create([
            'name' => 'Ken',
            'email' => 'luckykenlin@gmail.com',
            'password' => 'password',
        ]);

        // Only the verticals that have their own theme are seeded — the
        // product is built one vertical at a time, and the remaining
        // templates still render through the transitional default theme.
        // Add a slug here when its vertical theme ships. Photos are skipped
        // so a fresh clone with no provider key seeds in seconds; run
        // `demo:seed nail-salon` on its own to fill them in.
        Artisan::call('demo:seed', [
            'template' => [SiteTemplate::NailSalon->value],
            '--skip-photos' => true,
        ]);
    }
}
