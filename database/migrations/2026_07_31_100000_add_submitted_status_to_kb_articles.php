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
        // Schema builder instead of raw SQL: since version 11, Laravel
        // translates ->change() natively (without doctrine/dbal) for both
        // MySQL and SQLite (there via table rebuild) — so it works
        // correctly both in the real dev database and in the SQLite
        // in-memory test database from phpunit.xml.
        Schema::table('kb_articles', function (Blueprint $table) {
            $table->enum('status', ['draft', 'submitted', 'published', 'archived'])
                ->default('draft')
                ->change();
        });
    }

    public function down(): void
    {
        // Before rolling back, reset existing 'submitted' articles to
        // 'draft', since the old enum type no longer knows this value.
        DB::table('kb_articles')->where('status', 'submitted')->update(['status' => 'draft']);

        Schema::table('kb_articles', function (Blueprint $table) {
            $table->enum('status', ['draft', 'published', 'archived'])
                ->default('draft')
                ->change();
        });
    }
};
