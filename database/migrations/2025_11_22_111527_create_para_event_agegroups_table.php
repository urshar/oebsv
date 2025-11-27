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
        Schema::create('para_event_agegroups', function (Blueprint $table) {
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();

            $table->foreignId('para_event_id')
                ->constrained('para_events')
                ->cascadeOnDelete();

            // aus AGEGROUP @agegroupid (Lenex-intern, nicht zwingend global)
            $table->string('lenex_agegroupid', 20)->nullable();

            $table->string('name', 100);              // AGEGROUP @name (z.B. "ÖJM: PI")
            $table->char('gender', 1)->nullable();    // F/M/X oder leer
            $table->integer('age_min')->nullable();   // -1 => "keine Untergrenze"
            $table->integer('age_max')->nullable();   // -1 => "keine Obergrenze"

            // Handicap-Liste wie im Beispiel: "1,2,3,4,5,6,7,8,9,10"
            $table->string('handicap_raw', 255)->nullable();

            $table->timestamps();

            $table->index('lenex_agegroupid');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('para_event_agegroups');
    }
};
