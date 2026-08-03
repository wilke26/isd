<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'name' => 'Administrator',
                'slug' => 'admin',
                'permissions' => [
                    ['name' => 'Alle Assets verwalten',    'slug' => 'assets.manage'],
                    ['name' => 'Alle Tickets verwalten',   'slug' => 'tickets.manage'],
                    ['name' => 'Benutzer verwalten',       'slug' => 'users.manage'],
                    ['name' => 'Rollen verwalten',         'slug' => 'roles.manage'],
                    ['name' => 'Wissensdatenbank verwalten', 'slug' => 'kb.manage'],
                    ['name' => 'Lizenzen verwalten',       'slug' => 'licenses.manage'],
                ],
            ],
            [
                'name' => 'Agent',
                'slug' => 'agent',
                'permissions' => [
                    ['name' => 'Assets einsehen',          'slug' => 'assets.view'],
                    ['name' => 'Assets bearbeiten',        'slug' => 'assets.edit'],
                    ['name' => 'Tickets einsehen',         'slug' => 'tickets.view'],
                    ['name' => 'Tickets bearbeiten',       'slug' => 'tickets.edit'],
                    ['name' => 'Interne Kommentare',       'slug' => 'tickets.comment.internal'],
                    ['name' => 'Wissensdatenbank einsehen', 'slug' => 'kb.view'],
                    ['name' => 'Artikel erstellen',        'slug' => 'kb.create'],
                ],
            ],
            [
                'name' => 'Benutzer',
                'slug' => 'user',
                'permissions' => [
                    ['name' => 'Eigene Assets einsehen',   'slug' => 'assets.view.own'],
                    ['name' => 'Ticket erstellen',         'slug' => 'tickets.create'],
                    ['name' => 'Eigene Tickets einsehen',  'slug' => 'tickets.view.own'],
                    ['name' => 'Ticket kommentieren',      'slug' => 'tickets.comment'],
                    ['name' => 'Wissensdatenbank einsehen', 'slug' => 'kb.view'],
                ],
            ],
        ];

        foreach ($roles as $roleData) {
            $role = Role::firstOrCreate(
                ['slug' => $roleData['slug']],
                ['name' => $roleData['name']],
            );

            foreach ($roleData['permissions'] as $permData) {
                Permission::firstOrCreate(
                    ['role_id' => $role->id, 'slug' => $permData['slug']],
                    ['name' => $permData['name']],
                );
            }
        }
    }
}
