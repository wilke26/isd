<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kb_articles', function (Blueprint $table) {
            // Additive, timestamped addendum history for articles that are
            // already published/archived — the original main text (body)
            // remains unchanged, each addendum is appended here instead of
            // being mixed in.
            $table->text('addendum')->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('kb_articles', function (Blueprint $table) {
            $table->dropColumn('addendum');
        });
    }
};
