<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('para_entries', function (Blueprint $table) {
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();

            $table->foreignId('para_meet_id')
                ->constrained('para_meets')
                ->cascadeOnDelete();

            $table->foreignId('para_session_id')
                ->constrained('para_sessions')
                ->cascadeOnDelete();

            $table->foreignId('para_event_id')
                ->constrained('para_events')
                ->cascadeOnDelete();

            $table->foreignId('para_event_agegroup_id')
                ->nullable()
                ->constrained('para_event_agegroups')
                ->nullOnDelete();

            $table->foreignId('para_athlete_id')
                ->constrained('para_athletes')
                ->cascadeOnDelete();

            $table->foreignId('para_club_id')
                ->nullable()
                ->constrained('para_clubs')
                ->nullOnDelete();

            // LENEX IDs for debugging / cross-check
            $table->string('lenex_athleteid', 20)->nullable()->index();
            $table->string('lenex_eventid', 20)->nullable()->index(); // the ENTRY@eventid (10,20,30,...)

            // Entry time (both string + numeric ms)
            $table->string('entry_time', 20)->nullable(); // "00:01:45.71"
            $table->integer('entry_time_ms')->nullable();

            // Quali-meet info from <MEETINFO> (optional)
            $table->string('course', 10)->nullable();
            $table->date('qualifying_date')->nullable();
            $table->string('qualifying_meet_name', 255)->nullable();
            $table->string('qualifying_city', 255)->nullable();
            $table->string('qualifying_nation', 10)->nullable();

            $table->timestamps();

            // Ein Athlet soll pro Event nur eine Meldung haben
            $table->unique(
                ['para_event_id', 'para_athlete_id'],
                'para_entries_event_athlete_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('para_entries');
    }
};
