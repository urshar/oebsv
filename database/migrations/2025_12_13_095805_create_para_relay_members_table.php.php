<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('para_relay_members', function (Blueprint $table) {
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();

            $table->foreignId('para_relay_entry_id')
                ->constrained('para_relay_entries')
                ->cascadeOnDelete();

            $table->foreignId('para_athlete_id')
                ->nullable()
                ->constrained('para_athletes')
                ->nullOnDelete();

            // RELAYPOSITION@number (1..4)
            $table->unsignedInteger('leg')->nullable();

            // LENEX Athlete reference
            $table->string('lenex_athleteid')->nullable()->index();

            // Quick access (für Rekorde & schnelle Queries)
            $table->integer('leg_time_ms')->nullable();           // volle Legzeit
            $table->unsignedInteger('leg_distance')->nullable();  // z.B. 50/100
            $table->string('leg_stroke')->nullable();             // optional (Medley)

            $table->timestamps();

            $table->unique(
                ['para_relay_entry_id', 'leg'],
                'uniq_para_relay_member_leg'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('para_relay_members');
    }
};
