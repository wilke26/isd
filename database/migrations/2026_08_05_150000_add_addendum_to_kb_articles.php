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
            // Additiver, zeitgestempelter Ergänzungsverlauf für bereits
            // veröffentlichte/archivierte Artikel — der ursprüngliche
            // Haupttext (body) bleibt dabei unverändert, jede Ergänzung
            // wird hier angehängt statt hineingemischt.
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
