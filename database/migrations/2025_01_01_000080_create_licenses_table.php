<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('licenses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('vendor', 100);
            $table->string('product');
            // Stored encrypted via Laravel Encryption (cast in the model)
            $table->text('license_key')->nullable();
            $table->unsignedInteger('seats_total');
            $table->unsignedInteger('seats_used')->default(0);
            $table->date('expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('licenses');
    }
};
