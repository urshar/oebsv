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
        Schema::create('continents', function (Blueprint $table) {
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();
            $table->string('code', 5)->unique();
            $table->string('nameEn', 20);
            $table->string('nameDe', 20)->nullable();
            $table->timestamps();
        });

        Schema::create('nations', function (Blueprint $table) {
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();

            // Main names
            $table->string('nameEn', 200)->unique();
            $table->string('nameDe', 200)->nullable();

            // Federations
            $table->string('worldAquaNF', 250)->nullable();
            $table->string('worldAquaNFurl', 250)->nullable();
            $table->string('worldParaNF', 250)->nullable();
            $table->string('worldParaNFurl', 250)->nullable();

            // Continent relation
            $table->foreignId('continent_id')
                ->nullable()
                ->constrained('continents')
                ->nullOnDelete();

            // Codes (fixed length + unique)
            $table->char('ioc', 3)->nullable()->unique();   // IOC code (AUT, GER, ...)
            $table->char('iso2', 2)->nullable()->unique();  // 2-letter ISO
            $table->char('iso3', 3)->nullable()->unique();  // 3-letter ISO

            // Official names
            $table->string('officialNameEn', 250)->nullable();
            $table->string('officialShortEn', 250)->nullable();
            $table->string('officialNameDe', 250)->nullable();
            $table->string('officialShortDe', 250)->nullable();
            $table->string('officialNameCn', 250)->nullable();
            $table->string('officialShortCn', 250)->nullable();
            $table->string('officialNameFr', 250)->nullable();
            $table->string('officialShortFr', 250)->nullable();
            $table->string('officialNameAr', 250)->nullable();
            $table->string('officialShortAr', 250)->nullable();
            $table->string('officialNameRu', 250)->nullable();
            $table->string('officialShortRu', 250)->nullable();
            $table->string('officialNameEs', 250)->nullable();
            $table->string('officialShortEs', 250)->nullable();

            $table->string('subRegionName', 250)->nullable();
            $table->string('tld', 20)->nullable();                 // .at, .de, ...
            $table->string('currencyAlphabeticCode', 20)->nullable();
            $table->string('currencyName', 50)->nullable();
            $table->string('isIndependent', 30)->nullable();       // could later be converted to boolean
            $table->string('Capital', 250)->nullable();
            $table->string('IntermediateRegionName', 50)->nullable();

            $table->timestamps();

            // Helpful indexes for lookups
            $table->index('nameDe');
            $table->index('tld');
        });

        Schema::create('subregions', function (Blueprint $table) {
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();
            $table->string('abbr', 10)->nullable();
            $table->string('isoSubRegionCode', 10)->nullable();
            $table->string('nameDe', 100);
            $table->string('nameEn', 100)->nullable();

            $table->foreignId('nation_id')
                ->nullable()
                ->constrained('nations')
                ->nullOnDelete();

            $table->string('lsvCode', 10)->nullable();
            $table->string('bsvCode', 10)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // drop children before parents to satisfy FK constraints
        Schema::disableForeignKeyConstraints();

        Schema::dropIfExists('subregions');
        Schema::dropIfExists('nations');
        Schema::dropIfExists('continents');

        Schema::enableForeignKeyConstraints();
    }
};
