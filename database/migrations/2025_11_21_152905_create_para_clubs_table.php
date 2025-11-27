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
        Schema::create('para_clubs', function (Blueprint $table) {
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();

            // Globale externe IDs
            $table->string('swrid', 50)->nullable()->unique();      // LENEX / Swimrankings CLUB @swrid
            $table->unsignedBigInteger('tmId')->nullable()->unique(); // Team-Manager-ID eindeutig

            // Nationaler Code (z.B. Verbands-Kurzcode)
            $table->string('clubCode', 16)->nullable();

            // Namen
            $table->string('nameDe', 250);
            $table->string('shortNameDe', 100)->nullable();
            $table->string('nameEn', 250)->nullable();
            $table->string('shortNameEn', 100)->nullable();

            // Nation / Subregion
            $table->foreignId('nation_id')
                ->nullable()
                ->constrained('nations')
                ->nullOnDelete();

            $table->foreignId('subregion_id')
                ->nullable()
                ->constrained('subregions')
                ->nullOnDelete();

            // Alternative/alte Namen
            $table->string('altNameDe', 250)->nullable();
            $table->string('altShortNameDe', 100)->nullable();
            $table->string('altNameEn', 250)->nullable();
            $table->string('altShortNameEn', 100)->nullable();

            $table->timestamps();

            // === Dublettenvermeidung ===

            // Pro Nation: clubCode nur einmal
            $table->unique(['nation_id', 'clubCode']);

            // Pro Nation: deutscher Name nur einmal
            $table->unique(['nation_id', 'nameDe']);

            // Indexe für Suche
            $table->index('clubCode');
            $table->index('nameDe');
            $table->index('nameEn');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('para_clubs');
    }
};
