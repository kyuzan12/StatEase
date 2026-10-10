<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('likert_scales', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();                 // e.g. "5-point Agreement"
            $table->string('description', 500)->nullable();
            $table->unsignedTinyInteger('points');            // number of levels, 2 to 10
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('likert_scales');
    }
};
