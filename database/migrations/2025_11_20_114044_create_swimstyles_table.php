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
        Schema::create('swimstyles', function (Blueprint $table) {
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();

            // Distance in meters (LENEX: distance per swimmer)
            $table->unsignedSmallInteger('distance');

            // 1 for individual events, >1 for relays (e.g. 4 for 4x50/4x100)
            $table->unsignedTinyInteger('relaycount')->default(1);

            // Convenience flag
            $table->boolean('is_relay')->default(false);

            // LENEX stroke code like FREE, BACK, BREAST, FLY, MEDLEY
            $table->string('stroke_code', 20);

            // Human-readable stroke name or label (used in your views)
            $table->string('stroke', 50);

            // Optional localized names
            $table->string('stroke_name_en', 100)->nullable();
            $table->string('stroke_name_de', 100)->nullable();

            $table->timestamps();

            // One logical style per (distance, relaycount, stroke_code)
            $table->unique(
                ['distance', 'relaycount', 'stroke_code'],
                'swimstyles_dist_relay_strokecode_unique'
            );

            $table->index('stroke_code');
            $table->index('stroke');
            $table->index('is_relay');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('swimstyles');
    }
};
