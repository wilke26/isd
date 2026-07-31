<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Schema-Builder statt rohem SQL: Laravel übersetzt ->change() seit
        // Version 11 nativ (ohne doctrine/dbal) sowohl für MySQL als auch für
        // SQLite (dort per Tabellen-Neuaufbau) — funktioniert damit korrekt
        // sowohl in der echten Dev-Datenbank als auch in der SQLite-In-Memory-
        // Testdatenbank aus phpunit.xml.
        Schema::table('kb_articles', function (Blueprint $table) {
            $table->enum('status', ['draft', 'submitted', 'published', 'archived'])
                ->default('draft')
                ->change();
        });
    }

    public function down(): void
    {
        // Vor dem Zurückrollen bestehende 'submitted'-Artikel auf 'draft'
        // zurücksetzen, da der alte Enum-Typ diesen Wert nicht mehr kennt.
        DB::table('kb_articles')->where('status', 'submitted')->update(['status' => 'draft']);

        Schema::table('kb_articles', function (Blueprint $table) {
            $table->enum('status', ['draft', 'published', 'archived'])
                ->default('draft')
                ->change();
        });
    }
};
