<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('para_classifiers', function (Blueprint $table) {
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();

            $table->string('firstName', 100)->nullable();
            $table->string('lastName', 100)->nullable();

            $table->string('email', 190)->nullable();
            $table->string('phone', 50)->nullable();

            // TECH, MED, BOTH
            $table->string('type', 20)->nullable();

            // z.B. WPS-Classifer-ID
            $table->string('wps_id', 50)->nullable();

            // optional: Nation des Klassifizierers
            $table->foreignId('nation_id')
                ->nullable()
                ->constrained('nations')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['lastName', 'firstName'], 'para_classifiers_name_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('para_classifiers');
    }
};
