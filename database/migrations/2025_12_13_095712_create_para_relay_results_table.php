<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('para_relay_results', function (Blueprint $table) {
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');
            
            $table->id();

            $table->foreignId('para_relay_entry_id')
                ->constrained('para_relay_entries')
                ->cascadeOnDelete();

            $table->foreignId('para_meet_id')
                ->constrained('para_meets')
                ->cascadeOnDelete();

            $table->integer('time_ms')->nullable(); // Team-Endzeit

            $table->integer('rank')->nullable();
            $table->integer('heat')->nullable();
            $table->integer('lane')->nullable();
            $table->string('status')->nullable();   // OK/DSQ/DNS/...
            $table->integer('points')->nullable();

            // LENEX Meta
            $table->string('lenex_resultid')->nullable()->index();
            $table->string('lenex_heatid')->nullable();

            $table->timestamps();

            $table->unique(
                ['para_relay_entry_id', 'para_meet_id'],
                'uniq_para_relay_result'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('para_relay_results');
    }
};
