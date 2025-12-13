<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('para_relay_splits', function (Blueprint $table) {
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();

            $table->foreignId('para_relay_result_id')
                ->constrained('para_relay_results')
                ->cascadeOnDelete();

            $table->unsignedInteger('distance');        // z.B. 50, 100, 150, 200...
            $table->integer('cumulative_time_ms');      // Zeit ab Start (kumuliert)
            $table->integer('split_time_ms')->nullable(); // Segment seit vorherigem Split (optional, berechnet)
            $table->string('lenex_swimtime')->nullable(); // raw string

            $table->timestamps();

            $table->unique(
                ['para_relay_result_id', 'distance'],
                'uniq_para_relay_split_distance'
            );

            $table->index(['para_relay_result_id', 'distance'], 'idx_para_relay_splits_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('para_relay_splits');
    }
};
