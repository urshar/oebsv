<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('para_relay_leg_splits', function (Blueprint $table) {
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');
            
            $table->id();

            $table->foreignId('para_relay_member_id')
                ->constrained('para_relay_members')
                ->cascadeOnDelete();

            $table->unsignedInteger('distance_in_leg');     // 25, 50, 75, 100 ... innerhalb der Leg
            $table->integer('cumulative_time_ms');         // kumuliert innerhalb der Leg (0..LegEnde)
            $table->integer('split_time_ms')->nullable();  // Segment innerhalb der Leg seit vorherigem split
            $table->unsignedInteger('absolute_distance')->nullable(); // optional: Distanz ab Start (zur Rückverfolgung)

            $table->timestamps();

            $table->unique(
                ['para_relay_member_id', 'distance_in_leg'],
                'uniq_para_relay_leg_split'
            );

            $table->index(['para_relay_member_id', 'distance_in_leg'], 'idx_para_relay_leg_splits_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('para_relay_leg_splits');
    }
};
