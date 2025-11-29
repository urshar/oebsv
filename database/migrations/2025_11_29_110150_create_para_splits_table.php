<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('para_splits', function (Blueprint $table) {
            $table->id();

            $table->foreignId('para_result_id')
                ->constrained('para_results')
                ->cascadeOnDelete();

            $table->unsignedSmallInteger('distance');   // 50, 100, 150, ...
            $table->integer('time_ms');                 // Zwischenzeit

            $table->timestamps();

            $table->index(['para_result_id', 'distance']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('para_splits');
    }
};
