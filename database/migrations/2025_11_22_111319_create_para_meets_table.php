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
        Schema::create('para_meets', function (Blueprint $table) {
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();

            // aus <MEET ...>
            $table->string('name', 255);
            $table->string('city', 255)->nullable();

            // Bezug zur Nation-Tabelle (AUT aus Lenex @nation)
            $table->foreignId('nation_id')
                ->nullable()
                ->constrained('nations')
                ->nullOnDelete();

            // abgeleitet aus SESSIONS (min/max Datum)
            $table->date('from_date')->nullable();
            $table->date('to_date')->nullable();

            // Lenex-Metadaten
            $table->date('entry_start_date')->nullable();   // entrystartdate
            $table->date('entry_deadline')->nullable();     // deadline
            $table->date('withdraw_until')->nullable();     // withdrawuntil
            $table->string('entry_type', 20)->nullable();   // entrytype
            $table->string('course', 20)->nullable();       // course/SCM etc.
            $table->string('host_club', 255)->nullable();   // hostclub
            $table->string('organizer', 255)->nullable();   // organizer
            $table->string('organizer_url', 255)->nullable();
            $table->string('result_url', 255)->nullable();

            // zur Dublettenvermeidung bei mehreren Lenex derselben Veranstaltung:
            $table->string('meet_hash', 64)->nullable()->unique();

            // Original-XML Infos
            $table->date('lenex_revisiondate')->nullable();
            $table->timestamp('lenex_created')->nullable();

            $table->timestamps();

            // Für manuelle Suche
            $table->index(['name', 'city']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('para_meets');
    }
};
