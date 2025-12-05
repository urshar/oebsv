<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('para_record_import_candidates', function (Blueprint $table) {
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();

            // aus welcher Datei stammt der Import (zur Info)
            $table->string('source_file')->nullable();

            // Nation des Rekords
            $table->foreignId('nation_id')
                ->nullable()
                ->constrained('nations')
                ->nullOnDelete();

            // Metadaten der Recordlist
            $table->string('record_list_name');
            $table->string('record_type', 32)->nullable();
            $table->string('course', 4);
            $table->char('gender', 1);
            $table->unsignedTinyInteger('handicap')->nullable();
            $table->string('sport_class', 8)->nullable();
            $table->date('recordlist_updated_at')->nullable();

            // Altersbereich / Kategorie
            $table->unsignedTinyInteger('age_min')->nullable();
            $table->unsignedTinyInteger('age_max')->nullable();
            $table->string('agegroup_code', 8)->nullable();

            // Schwimmbewerb
            $table->unsignedSmallInteger('distance');
            $table->string('stroke', 16);
            $table->unsignedTinyInteger('relaycount')->default(1);
            $table->boolean('is_relay')->default(false);

            // Zeit
            $table->unsignedInteger('swimtime_ms');
            $table->date('swum_at')->nullable();

            // Wettkampf
            $table->string('status', 32)->nullable();
            $table->string('meet_name')->nullable();
            $table->string('meet_nation', 3)->nullable();

            // Rohdaten ATHLETE aus LENEX
            $table->string('athlete_swrid')->nullable();
            $table->unsignedInteger('athlete_tmid')->nullable();
            $table->string('athlete_license')->nullable();
            $table->string('athlete_firstname')->nullable();
            $table->string('athlete_lastname')->nullable();
            $table->string('athlete_gender', 1)->nullable();
            $table->date('athlete_birthdate')->nullable();

            // Rohdaten CLUB aus LENEX
            $table->string('club_swrid')->nullable();
            $table->string('club_code')->nullable();
            $table->string('club_name')->nullable();
            $table->string('club_nation', 3)->nullable();

            // was fehlt?
            $table->boolean('missing_athlete')->default(false);
            $table->boolean('missing_club')->default(false);

            // spätere Zuordnung
            $table->foreignId('para_athlete_id')
                ->nullable()
                ->constrained('para_athletes')
                ->nullOnDelete();

            $table->foreignId('para_club_id')
                ->nullable()
                ->constrained('para_clubs')
                ->nullOnDelete();

            $table->foreignId('para_record_id')
                ->nullable()
                ->constrained('para_records')
                ->nullOnDelete();

            $table->string('resolution_status', 16)->default('pending'); // pending/resolved/ignored
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();

            $table->index(['resolution_status']);
        });

        Schema::create('para_record_import_candidate_splits', function (Blueprint $table) {
            $table->id();

            $table->foreignId('para_record_import_candidate_id')
                ->constrained('para_record_import_candidates')
                ->cascadeOnDelete();

            $table->unsignedSmallInteger('distance');
            $table->unsignedTinyInteger('order')->default(1);
            $table->unsignedInteger('swimtime_ms');

            $table->timestamps();

            $table->index(['para_record_import_candidate_id', 'distance']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('para_record_import_candidate_splits');
        Schema::dropIfExists('para_record_import_candidates');
    }
};
