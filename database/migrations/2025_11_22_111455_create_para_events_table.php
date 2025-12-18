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
        Schema::create('para_events', function (Blueprint $table) {
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();

            $table->foreignId('para_session_id')
                ->constrained('para_sessions')
                ->cascadeOnDelete();

            // aus EVENT
            $table->string('lenex_eventid', 20)->nullable()->index(); // EVENT @eventid
            $table->unsignedInteger('number')->nullable();           // EVENT @number
            $table->unsignedInteger('order')->nullable();            // EVENT @order
            $table->string('round', 10)->nullable();                 // TIM, PRE, FIN ...

            // SWIMSTYLE
            $table->foreignId('swimstyle_id')
                ->nullable()
                ->after('para_session_id')
                ->constrained('swimstyles')
                ->nullOnDelete();

            // optional: Startgeld
            $table->decimal('fee')->nullable();
            $table->string('fee_currency', 10)->nullable();

            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('para_events');
    }
};
