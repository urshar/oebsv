<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('para_athlete_classifications', function (Blueprint $table) {
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();

            $table->foreignId('para_athlete_id')
                ->constrained('para_athletes')
                ->cascadeOnDelete();

            // Classification data
            $table->date('classification_date')->nullable();
            $table->string('location', 150)->nullable();
            $table->boolean('is_international')->default(false);

            // WPS license at time of classification (can copy from athlete)
            $table->string('wps_license', 50)->nullable();

            // Classes
            $table->string('sportclass_s', 10)->nullable();
            $table->string('sportclass_sb', 10)->nullable();
            $table->string('sportclass_sm', 10)->nullable();
            $table->string('sportclass_exception', 50)->nullable();

            // Status (Confirmed / Review / New / National / etc.)
            $table->string('status', 50)->nullable();

            // Panel: two technical classifiers + one medical
            $table->string('tech_classifier_1', 150)->nullable();
            $table->string('tech_classifier_2', 150)->nullable();
            $table->string('med_classifier', 150)->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['para_athlete_id', 'classification_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('para_athlete_classifications');
    }
};
