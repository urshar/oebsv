<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('para_athlete_classification_classifier', function (Blueprint $table) {
            $table->id();

            $table->foreignId('para_athlete_classification_id')
                ->constrained('para_athlete_classifications')
                ->cascadeOnDelete();

            $table->foreignId('para_classifier_id')
                ->constrained('para_classifiers')
                ->cascadeOnDelete();

            // TECH1, TECH2, MED
            $table->string('role', 20);

            $table->timestamps();

            $table->unique(
                ['para_athlete_classification_id', 'para_classifier_id', 'role'],
                'para_classif_classifier_role_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('para_athlete_classification_classifier');
    }
};
