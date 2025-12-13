<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('para_relay_entries', function (Blueprint $table) {
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();

            $table->foreignId('para_meet_id')
                ->constrained('para_meets')
                ->cascadeOnDelete();

            $table->foreignId('para_session_id')
                ->nullable()
                ->constrained('para_sessions')
                ->nullOnDelete();

            $table->foreignId('para_event_id')
                ->constrained('para_events')
                ->cascadeOnDelete();

            $table->foreignId('para_club_id')
                ->nullable()
                ->constrained('para_clubs')
                ->nullOnDelete();

            // LENEX Meta
            $table->string('lenex_eventid')->nullable()->index();
            $table->string('lenex_clubid')->nullable()->index();
            $table->string('lenex_relay_number')->nullable(); // RELAY@number
            $table->string('gender')->nullable();             // optional

            // Entry time
            $table->string('entry_time')->nullable();     // raw
            $table->integer('entry_time_ms')->nullable(); // normalized

            $table->timestamps();

            $table->unique(
                ['para_meet_id', 'para_event_id', 'para_club_id', 'lenex_relay_number'],
                'uniq_para_relay_entry'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('para_relay_entries');
    }
};
