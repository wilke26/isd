<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->index(['requester_id', 'created_at'], 'tickets_requester_created_index');
        });

        Schema::table('kb_articles', function (Blueprint $table) {
            // The unique slug index already serves slug lookups. Replace the
            // redundant non-unique slug index and the single-column status
            // index with the index used by published/status-filtered lists.
            $table->dropIndex('kb_articles_slug_index');
            $table->dropIndex('kb_articles_status_index');
            $table->index(['status', 'published_at'], 'kb_articles_status_published_index');
        });
    }

    public function down(): void
    {
        Schema::table('kb_articles', function (Blueprint $table) {
            $table->dropIndex('kb_articles_status_published_index');
            $table->index('status');
            $table->index('slug');
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex('tickets_requester_created_index');
        });
    }
};
