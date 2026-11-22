<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('court_id')->constrained()->cascadeOnDelete();
            $table->date('play_date');
            $table->unsignedTinyInteger('start_hour');            // 0-23, slot 1 jam
            $table->unsignedInteger('price');                     // snapshot harga saat booking
            $table->string('status', 20)->default('pending')->index();

            // Kunci anti double booking. Terisi saat booking menahan slot,
            // NULL saat dibatalkan/ditolak. UNIQUE mengizinkan banyak NULL.
            $table->string('active_slot')->nullable()->unique();

            $table->timestamps();

            $table->index(['court_id', 'play_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
