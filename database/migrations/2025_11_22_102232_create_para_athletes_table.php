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
        Schema::create('para_athletes', function (Blueprint $table) {
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();

            // Externe IDs / Schlüssel
            $table->unsignedBigInteger('tmId')->nullable()->unique();       // TeamManager-ID (soll unique sein)
            $table->string('swrid', 50)->nullable()->unique();              // z.B. Swimrankings-ID
            $table->string('oebsv_license', 50)->nullable()->unique();
            $table->string('wps_license', 50)->nullable()->after('oebsv_license');

            // Basisdaten
            $table->string('firstName', 100);
            $table->string('lastName', 100);
            $table->enum('gender', ['M', 'F', 'X'])->nullable();
            $table->date('birthdate')->nullable();

            // Zugehörigkeit
            $table->foreignId('para_club_id')
                ->nullable()
                ->constrained('para_clubs')
                ->nullOnDelete();

            $table->foreignId('nation_id')
                ->nullable()
                ->constrained('nations')
                ->nullOnDelete();

            // Sportklassen (S / SB / SM) + Ausnahmen
            // Werte kommen aus <HANDICAP free/breast/medley exception="...">
            $table->string('sportclass_s', 10)->nullable();          // z.B. S8
            $table->string('sportclass_sb', 10)->nullable();         // z.B. SB7
            $table->string('sportclass_sm', 10)->nullable();         // z.B. SM8
            $table->string('sportclass_exception', 50)->nullable();  // z.B. T, B, "Review" etc.

            // Optionale Kontaktdaten (falls du sie später brauchst)
            $table->string('email', 190)->nullable();
            $table->string('phone', 50)->nullable();

            $table->timestamps();

            // Hilfsindizes für Suchen
            $table->index(['lastName', 'firstName']);
            $table->index('birthdate');
            $table->index('nation_id');
            $table->index('para_club_id');
            $table->index(
                ['firstName', 'lastName', 'birthdate', 'gender', 'nation_id'],
                'para_athletes_name_dob_gender_nation_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('para_athletes');
    }
};
