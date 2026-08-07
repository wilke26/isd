<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Asset;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketComment;
use App\Models\TicketHistory;
use App\Models\User;
use Illuminate\Database\Seeder;

class TicketSeeder extends Seeder
{
    public function run(): void
    {
        // ─── Categories ───────────────────────────────────────────────
        $hardware = TicketCategory::firstOrCreate(['name' => 'Hardware'], ['parent_id' => null]);
        $software = TicketCategory::firstOrCreate(['name' => 'Software'], ['parent_id' => null]);
        $network = TicketCategory::firstOrCreate(['name' => 'Netzwerk'], ['parent_id' => null]);
        $account = TicketCategory::firstOrCreate(['name' => 'Zugangsdaten'], ['parent_id' => null]);

        TicketCategory::firstOrCreate(['name' => 'Laptop/PC'], ['parent_id' => $hardware->id]);
        TicketCategory::firstOrCreate(['name' => 'Drucker'], ['parent_id' => $hardware->id]);
        TicketCategory::firstOrCreate(['name' => 'Installation'], ['parent_id' => $software->id]);
        TicketCategory::firstOrCreate(['name' => 'Absturz'], ['parent_id' => $software->id]);
        TicketCategory::firstOrCreate(['name' => 'VPN'], ['parent_id' => $network->id]);
        TicketCategory::firstOrCreate(['name' => 'Passwort-Reset'], ['parent_id' => $account->id]);

        // ─── Load users ───────────────────────────────────────────────
        $admin = User::where('email', 'admin@isd.local')->first();
        $anna = User::where('email', 'a.mueller@isd.local')->first();
        $ben = User::where('email', 'b.schmidt@isd.local')->first();
        $clara = User::where('email', 'c.weber@isd.local')->first();
        $david = User::where('email', 'd.bauer@isd.local')->first();
        $eva = User::where('email', 'e.fischer@isd.local')->first();

        $nb001 = Asset::where('asset_tag', 'NB-001')->first();
        $nb002 = Asset::where('asset_tag', 'NB-002')->first();

        // ─── Tickets ──────────────────────────────────────────────────
        $ticket1 = Ticket::firstOrCreate(
            ['title' => 'Laptop startet nicht mehr'],
            [
                'requester_id' => $clara->id,
                'assignee_id' => $anna->id,
                'asset_id' => $nb001->id,
                'category_id' => TicketCategory::where('name', 'Laptop/PC')->first()->id,
                'description' => 'Mein MacBook Pro lässt sich seit heute Morgen nicht mehr einschalten. '
                    . 'Das Ladekabel ist angeschlossen, aber keine Reaktion.',
                'status' => TicketStatus::InProgress,
                'priority' => TicketPriority::High,
                'due_at' => now()->addDays(1),
            ],
        );

        // Comment and history for ticket 1
        TicketComment::firstOrCreate(
            ['ticket_id' => $ticket1->id, 'user_id' => $anna->id, 'is_internal' => false],
            ['body' => 'Ich habe das Ticket angenommen und schaue mir das Gerät heute noch an. '
                . 'Bitte bringe das Laptop in den IT-Raum (Raum 112).'],
        );

        TicketComment::firstOrCreate(
            ['ticket_id' => $ticket1->id, 'user_id' => $anna->id, 'is_internal' => true],
            ['body' => 'Intern: Vermutlich defektes Netzteil oder Akku-Tiefentladung. '
                . 'SMC-Reset versuchen, sonst Hardware-Check.'],
        );

        TicketHistory::firstOrCreate(
            ['ticket_id' => $ticket1->id, 'field' => 'status', 'new_value' => 'in_progress'],
            ['user_id' => $anna->id, 'old_value' => 'open'],
        );

        // Ticket 2
        $ticket2 = Ticket::firstOrCreate(
            ['title' => 'VPN-Verbindung bricht ständig ab'],
            [
                'requester_id' => $david->id,
                'assignee_id' => $ben->id,
                'asset_id' => $nb002->id,
                'category_id' => TicketCategory::where('name', 'VPN')->first()->id,
                'description' => 'Die VPN-Verbindung wird alle 10–15 Minuten unterbrochen. '
                    . 'Betriebssystem: Windows 11, VPN-Client: Cisco AnyConnect 4.10.',
                'status' => TicketStatus::Open,
                'priority' => TicketPriority::Medium,
                'due_at' => now()->addDays(3),
            ],
        );

        TicketComment::firstOrCreate(
            ['ticket_id' => $ticket2->id, 'user_id' => $david->id, 'is_internal' => false],
            ['body' => 'Das Problem tritt sowohl im Homeoffice als auch im Büronetz auf.'],
        );

        // Ticket 3 — already resolved
        $ticket3 = Ticket::firstOrCreate(
            ['title' => 'Passwort vergessen — Outlook'],
            [
                'requester_id' => $eva->id,
                'assignee_id' => $anna->id,
                'category_id' => TicketCategory::where('name', 'Passwort-Reset')->first()->id,
                'description' => 'Ich komme nicht mehr in mein Outlook-Konto. '
                    . 'Bitte Passwort zurücksetzen.',
                'status' => TicketStatus::Resolved,
                'priority' => TicketPriority::Low,
                'resolved_at' => now()->subHours(2),
            ],
        );

        TicketComment::firstOrCreate(
            ['ticket_id' => $ticket3->id, 'user_id' => $anna->id, 'is_internal' => false],
            ['body' => 'Passwort wurde zurückgesetzt. Bitte beim nächsten Login ein neues Passwort vergeben.'],
        );

        TicketHistory::firstOrCreate(
            ['ticket_id' => $ticket3->id, 'field' => 'status', 'new_value' => 'resolved'],
            ['user_id' => $anna->id, 'old_value' => 'open'],
        );

        // Ticket 4 — open, not yet assigned
        Ticket::firstOrCreate(
            ['title' => 'Software-Installation: Adobe Acrobat Pro'],
            [
                'requester_id' => $clara->id,
                'assignee_id' => null,
                'category_id' => TicketCategory::where('name', 'Installation')->first()->id,
                'description' => 'Ich benötige Adobe Acrobat Pro für die Bearbeitung von PDF-Formularen. '
                    . 'Bitte Installation auf NB-001 veranlassen.',
                'status' => TicketStatus::Open,
                'priority' => TicketPriority::Low,
                'due_at' => now()->addWeek(),
            ],
        );
    }
}
