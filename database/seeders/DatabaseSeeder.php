<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,   // Rollen & Berechtigungen zuerst
            UserSeeder::class,   // Benutzer benötigen Rollen
            AssetSeeder::class,  // Assets & Zuweisungen
            TicketSeeder::class, // Tickets benötigen User & Assets
            KbSeeder::class,     // Wissensdatenbank
        ]);
    }
}
