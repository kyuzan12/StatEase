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
        Schema::create('survey_columns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');          // 0 = first column in the CSV
            $table->string('name', 500);                       // the CSV header (question text)
            $table->string('type', 20)->default('ignore');     // likert, profile, or ignore
            $table->timestamps();

            $table->unique(['survey_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('survey_columns');
    }
};
