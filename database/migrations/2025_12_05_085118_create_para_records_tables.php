<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('para_records', function (Blueprint $table) {
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();

            // Beziehungen
            $table->foreignId('para_athlete_id')
                ->nullable()
                ->constrained('para_athletes')
                ->nullOnDelete();

            $table->foreignId('para_club_id')
                ->nullable()
                ->constrained('para_clubs')
                ->nullOnDelete();

            // Nation, der der Rekord gehört (AUT etc.)
            $table->foreignId('nation_id')
                ->nullable()
                ->constrained('nations')
                ->nullOnDelete();

            // Metadaten der Recordlist
            $table->string('record_list_name');             // z.B. "Österreichische Rekorde"
            $table->string('record_type', 32)->nullable();  // z.B. "AUT.OP", "AUT.JG"
            $table->string('course', 4);                    // LCM / SCM
            $table->char('gender', 1);                      // M / F / X
            $table->unsignedTinyInteger('handicap')->nullable();   // 1–15, 21, ...

            $table->string('sport_class', 8)->nullable();   // z.B. "S14"
            $table->date('recordlist_updated_at')->nullable();

            // Altersbereich / Kategorie (JG/OP)
            $table->unsignedTinyInteger('age_min')->nullable();    // normalisiert: -1 -> 0
            $table->unsignedTinyInteger('age_max')->nullable();    // normalisiert: -1 -> 99
            $table->string('agegroup_code', 8)->nullable();        // "JG" / "OP"

            // Schwimmbewerb
            $table->unsignedSmallInteger('distance');       // 25, 50, 100, ...
            $table->string('stroke', 16);                   // FREE, BACK, BREAST, FLY, MEDLEY
            $table->unsignedTinyInteger('relaycount')->default(1); // 1 = Einzel, >1 Staffel
            $table->boolean('is_relay')->default(false);

            // Rekordzeit in Millisekunden
            $table->unsignedInteger('swimtime_ms');

            // Rekord-Datum / Wettkampf
            $table->date('swum_at')->nullable();
            $table->string('status', 32)->nullable();       // z.B. "OK"
            $table->string('meet_name')->nullable();
            $table->string('meet_nation', 3)->nullable();   // Austragungsland als Code, kann String bleiben

            // redundante Halter-Infos (für Anzeige, unabhängig von Relations)
            $table->string('holder_firstname')->nullable();
            $table->string('holder_lastname')->nullable();
            $table->unsignedSmallInteger('holder_year_of_birth')->nullable();

            $table->timestamps();

            // sinnvolle Indizes
            $table->index(['sport_class', 'gender', 'course', 'agegroup_code']);
            $table->index(['distance', 'stroke', 'relaycount']);
            $table->index(['record_type']);
        });

        Schema::create('para_record_splits', function (Blueprint $table) {
            $table->id();

            $table->foreignId('para_record_id')
                ->constrained('para_records')
                ->cascadeOnDelete();

            $table->unsignedSmallInteger('distance');   // SPLIT@distance
            $table->unsignedTinyInteger('order')->default(1);
            $table->unsignedInteger('swimtime_ms');     // Zeit in ms

            $table->timestamps();

            $table->index(['para_record_id', 'distance']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('para_record_splits');
        Schema::dropIfExists('para_records');
    }
};
