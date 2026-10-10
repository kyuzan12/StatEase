<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('likert_scale_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('likert_scale_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('value');             // 1 = lowest
            $table->string('label', 100);                      // the answer choice, e.g. "Strongly Agree"
            $table->string('interpretation', 100)->nullable(); // optional wording for results, e.g. "Very Satisfactory"
            $table->timestamps();

            $table->unique(['likert_scale_id', 'value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('likert_scale_levels');
    }
};
