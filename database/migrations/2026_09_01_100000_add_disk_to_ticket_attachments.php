<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_attachments', function (Blueprint $table) {
            // Existing files were stored under storage/app/private. The new
            // attachments disk deliberately has the same root, so old rows
            // remain readable while every new row records its actual disk.
            $table->string('disk', 50)->default('attachments')->after('filename');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_attachments', function (Blueprint $table) {
            $table->dropColumn('disk');
        });
    }
};
