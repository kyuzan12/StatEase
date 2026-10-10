<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Survey extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'description',
        'likert_scale_id',
        'original_filename',
        'respondent_count',
        'is_mapped',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'respondent_count' => 'integer',
        'is_mapped' => 'boolean',
    ];

    /**
     * The user who uploaded the survey.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The Likert scale used by this survey's Likert items (e.g. 5-point Agreement).
     */
    public function scale(): BelongsTo
    {
        return $this->belongsTo(LikertScale::class, 'likert_scale_id');
    }

    /**
     * All columns (questions) of the uploaded CSV, in their original order.
     */
    public function columns(): HasMany
    {
        return $this->hasMany(SurveyColumn::class)->orderBy('position');
    }

    /**
     * Columns marked as Likert-scale items (e.g. 1 to 5).
     */
    public function likertColumns(): HasMany
    {
        return $this->columns()->where('type', SurveyColumn::TYPE_LIKERT);
    }

    /**
     * Columns marked as profile questions (e.g. sex, year level).
     */
    public function profileColumns(): HasMany
    {
        return $this->columns()->where('type', SurveyColumn::TYPE_PROFILE);
    }

    /**
     * One record per row of the uploaded CSV.
     */
    public function respondents(): HasMany
    {
        return $this->hasMany(Respondent::class);
    }
}
