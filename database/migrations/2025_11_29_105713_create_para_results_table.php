<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('para_results', function (Blueprint $table) {
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();

            // Verknüpfung zur Meldung
            $table->foreignId('para_entry_id')
                ->constrained('para_entries')
                ->cascadeOnDelete();

            // optional zusätzliche Abkürzung
            $table->foreignId('para_meet_id')
                ->constrained('para_meets')
                ->cascadeOnDelete();

            // Zeit als Hundertstel-Millis oder ähnlich
            $table->integer('time_ms')->nullable();          // Gesamtzeit
            $table->integer('reaction_time_ms')->nullable(); // Startreaktion

            $table->unsignedSmallInteger('rank')->nullable();      // Platz
            $table->unsignedSmallInteger('heat')->nullable();      // Lauf
            $table->unsignedSmallInteger('lane')->nullable();      // Bahn
            $table->string('round', 20)->nullable();               // Vorlauf / Finale etc.
            $table->string('status', 20)->nullable();              // OK, DQ, DNS, DNF ...
            $table->integer('points')->nullable();                 // ParaPoints/Normpunkte

            $table->timestamps();

            $table->index(['para_meet_id', 'rank']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('para_results');
    }
};
