<?php

use Database\Seeders\LikertScaleSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Surveys used to store only "scale_points" (4 or 5). Now each survey points to a LikertScale,
 * which holds the labels too. Existing surveys are linked to the default scale with the same points.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surveys', function (Blueprint $table) {
            $table->foreignId('likert_scale_id')->nullable()->after('description')->constrained()->restrictOnDelete();
        });

        (new LikertScaleSeeder)->run();

        foreach (DB::table('surveys')->get(['id', 'scale_points']) as $survey) {
            $scaleId = DB::table('likert_scales')->where('points', $survey->scale_points)->orderBy('id')->value('id');
            DB::table('surveys')->where('id', $survey->id)->update(['likert_scale_id' => $scaleId]);
        }

        Schema::table('surveys', function (Blueprint $table) {
            $table->foreignId('likert_scale_id')->nullable(false)->change();
            $table->dropColumn('scale_points');
        });
    }

    public function down(): void
    {
        Schema::table('surveys', function (Blueprint $table) {
            $table->unsignedTinyInteger('scale_points')->default(4)->after('description');
        });

        foreach (DB::table('surveys')->get(['id', 'likert_scale_id']) as $survey) {
            $points = DB::table('likert_scales')->where('id', $survey->likert_scale_id)->value('points');
            DB::table('surveys')->where('id', $survey->id)->update(['scale_points' => $points]);
        }

        Schema::table('surveys', function (Blueprint $table) {
            $table->dropConstrainedForeignId('likert_scale_id');
        });
    }
};
