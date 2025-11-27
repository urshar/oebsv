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
        Schema::create('para_sessions', function (Blueprint $table) {
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();

            $table->foreignId('para_meet_id')
                ->constrained('para_meets')
                ->cascadeOnDelete();

            $table->unsignedInteger('number')->nullable();   // SESSION @number
            $table->date('date')->nullable();                // SESSION @date
            $table->time('start_time')->nullable();          // SESSION @daytime

            // Warmup / Meetings aus deinem Beispiel
            $table->time('warmup_from')->nullable();
            $table->time('warmup_until')->nullable();
            $table->time('official_meeting')->nullable();
            $table->time('teamleader_meeting')->nullable();

            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('para_sessions');
    }
};
