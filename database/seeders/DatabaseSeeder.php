<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

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

        // The eight showcase sites the /templates gallery links at. Photos are
        // skipped here so a fresh clone with no provider key seeds in seconds;
        // run `demo:seed` on its own to fill them in.
        $this->command->call('demo:seed', ['--skip-photos' => true]);
    }
}
