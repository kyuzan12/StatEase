<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Respondent extends Model
{
    protected $fillable = [
        'survey_id',
        'row_number',
        'answers',
    ];

    protected $casts = [
        'row_number' => 'integer',
        'answers' => 'array',   // JSON in the database <-> PHP array in our code
    ];

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    /**
     * Get this respondent's answer for one column.
     */
    public function answerFor(SurveyColumn $column): ?string
    {
        return $this->answers[$column->position] ?? null;
    }
}
