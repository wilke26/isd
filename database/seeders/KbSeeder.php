<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ArticleStatus;
use App\Models\KbArticle;
use App\Models\KbCategory;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class KbSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@isd.local')->first();
        $anna  = User::where('email', 'a.mueller@isd.local')->first();

        // ─── Kategorien ────────────────────────────────────────────
        $hardware = KbCategory::firstOrCreate(
            ['slug' => 'hardware'],
            ['name' => 'Hardware', 'parent_id' => null],
        );

        $software = KbCategory::firstOrCreate(
            ['slug' => 'software'],
            ['name' => 'Software', 'parent_id' => null],
        );

        $network = KbCategory::firstOrCreate(
            ['slug' => 'netzwerk'],
            ['name' => 'Netzwerk', 'parent_id' => null],
        );

        $account = KbCategory::firstOrCreate(
            ['slug' => 'zugangsdaten'],
            ['name' => 'Zugangsdaten & Sicherheit', 'parent_id' => null],
        );

        // ─── Tags ──────────────────────────────────────────────────
        $tagVpn      = Tag::firstOrCreate(['slug' => 'vpn'],      ['name' => 'VPN']);
        $tagWindows  = Tag::firstOrCreate(['slug' => 'windows'],   ['name' => 'Windows']);
        $tagMacos    = Tag::firstOrCreate(['slug' => 'macos'],     ['name' => 'macOS']);
        $tagPasswort = Tag::firstOrCreate(['slug' => 'passwort'],  ['name' => 'Passwort']);
        $tagNetzwerk = Tag::firstOrCreate(['slug' => 'netzwerk'],  ['name' => 'Netzwerk']);

        // ─── Artikel ───────────────────────────────────────────────
        $article1 = KbArticle::firstOrCreate(
            ['slug' => 'vpn-einrichten-windows'],
            [
                'author_id'    => $anna->id,
                'category_id'  => $network->id,
                'title'        => 'VPN einrichten unter Windows 11',
                'body'         => "# VPN einrichten unter Windows 11\n\n"
                    . "## Voraussetzungen\n\n"
                    . "- Cisco AnyConnect 4.10 oder neuer\n"
                    . "- Gültige Active-Directory-Zugangsdaten\n"
                    . "- Internetverbindung\n\n"
                    . "## Installation\n\n"
                    . "1. Lade den AnyConnect-Client vom Intranet unter `\\\\fileserver\\software\\vpn` herunter.\n"
                    . "2. Führe das Installationsprogramm als Administrator aus.\n"
                    . "3. Starte den Rechner neu.\n\n"
                    . "## Verbindung herstellen\n\n"
                    . "1. Öffne Cisco AnyConnect.\n"
                    . "2. Gib als Server `vpn.example.com` ein.\n"
                    . "3. Melde dich mit deinen AD-Zugangsdaten an.\n\n"
                    . "## Häufige Probleme\n\n"
                    . "**Verbindung bricht ab**: Prüfe ob der AnyConnect-Dienst läuft (`services.msc`).\n"
                    . "**Falsches Passwort**: Stelle sicher, dass dein AD-Passwort nicht abgelaufen ist.",
                'status'       => ArticleStatus::Published,
                'published_at' => now()->subDays(10),
            ],
        );
        $article1->tags()->syncWithoutDetaching([$tagVpn->id, $tagWindows->id, $tagNetzwerk->id]);

        $article2 = KbArticle::firstOrCreate(
            ['slug' => 'passwort-zuruecksetzen'],
            [
                'author_id'    => $admin->id,
                'category_id'  => $account->id,
                'title'        => 'Passwort zurücksetzen — Self-Service und IT-Support',
                'body'         => "# Passwort zurücksetzen\n\n"
                    . "## Self-Service (empfohlen)\n\n"
                    . "Über das Self-Service-Portal unter `https://accounts.example.com` kannst du dein "
                    . "Passwort selbst zurücksetzen, sofern du deine Sicherheitsfragen hinterlegt hast.\n\n"
                    . "## Über den IT-Support\n\n"
                    . "Falls der Self-Service nicht möglich ist, erstelle ein Ticket mit der Kategorie "
                    . "**Zugangsdaten → Passwort-Reset**. Der IT-Support setzt das Passwort innerhalb "
                    . "eines Werktages zurück.\n\n"
                    . "## Passwortrichtlinie\n\n"
                    . "- Mindestens 12 Zeichen\n"
                    . "- Groß- und Kleinbuchstaben\n"
                    . "- Mindestens eine Zahl und ein Sonderzeichen\n"
                    . "- Gültigkeit: 90 Tage",
                'status'       => ArticleStatus::Published,
                'published_at' => now()->subDays(30),
            ],
        );
        $article2->tags()->syncWithoutDetaching([$tagPasswort->id]);

        $article3 = KbArticle::firstOrCreate(
            ['slug' => 'macos-smc-reset'],
            [
                'author_id'    => $anna->id,
                'category_id'  => $hardware->id,
                'title'        => 'MacBook: SMC-Reset bei Startproblemen',
                'body'         => "# SMC-Reset beim MacBook\n\n"
                    . "Der SMC (System Management Controller) steuert Hardware-Funktionen wie "
                    . "Lüfter, Akku und Netzteil. Ein Reset kann bei Startproblemen helfen.\n\n"
                    . "## MacBook mit Apple Silicon (M1/M2/M3/M4)\n\n"
                    . "1. Fahre das MacBook vollständig herunter.\n"
                    . "2. Warte 30 Sekunden.\n"
                    . "3. Schalte es wieder ein.\n\n"
                    . "Bei Apple Silicon gibt es keinen manuellen SMC-Reset — ein Neustart reicht.\n\n"
                    . "## MacBook mit Intel-Prozessor\n\n"
                    . "1. Fahre das MacBook herunter.\n"
                    . "2. Halte `Shift + Ctrl + Option (links) + Ein/Aus` für 10 Sekunden gedrückt.\n"
                    . "3. Lasse alle Tasten los.\n"
                    . "4. Schalte das MacBook normal ein.",
                'status'       => ArticleStatus::Published,
                'published_at' => now()->subDays(5),
            ],
        );
        $article3->tags()->syncWithoutDetaching([$tagMacos->id, $tagWindows->id]);

        // Entwurf — noch nicht veröffentlicht
        KbArticle::firstOrCreate(
            ['slug' => 'onboarding-neue-mitarbeiter'],
            [
                'author_id'    => $admin->id,
                'category_id'  => $account->id,
                'title'        => 'Onboarding: IT-Ausstattung für neue Mitarbeiter',
                'body'         => "# Onboarding-Checkliste (Entwurf)\n\n"
                    . "Dieser Artikel wird noch bearbeitet.",
                'status'       => ArticleStatus::Draft,
                'published_at' => null,
            ],
        );
    }
}
