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
        Schema::table('tickets', function (Blueprint $table) {
            $table->enum('status', ['open', 'in_progress', 'waiting_for_requester', 'resolved', 'closed'])
                ->default('open')
                ->change();
        });
    }

    public function down(): void
    {
        // Vor dem Zurückrollen bestehende 'waiting_for_requester'-Tickets auf
        // 'in_progress' zurücksetzen, da der alte Enum-Typ den Wert nicht kennt.
        DB::table('tickets')->where('status', 'waiting_for_requester')->update(['status' => 'in_progress']);

        Schema::table('tickets', function (Blueprint $table) {
            $table->enum('status', ['open', 'in_progress', 'resolved', 'closed'])
                ->default('open')
                ->change();
        });
    }
};
