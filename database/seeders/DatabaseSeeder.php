<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Sem WithoutModelEvents: os models dependem do evento "saving" para gerar slug.
     */
    public function run(): void
    {
        $this->call(CatalogBaseSeeder::class);

        if (! app()->environment('local')) {
            return;
        }

        $this->call(DemoCatalogSeeder::class);

        // Admin de desenvolvimento (senha "password"). Só existe em ambiente local.
        if (! User::where('email', 'admin@lara.test')->exists()) {
            User::factory()->admin()->create([
                'name' => 'Admin Local',
                'email' => 'admin@lara.test',
            ]);
        }
    }
}
