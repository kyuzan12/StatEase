<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveyColumn extends Model
{
    public const TYPE_LIKERT = 'likert';
    public const TYPE_PROFILE = 'profile';
    public const TYPE_IGNORE = 'ignore';

    public const TYPES = [
        self::TYPE_LIKERT => 'Likert item',
        self::TYPE_PROFILE => 'Profile question',
        self::TYPE_IGNORE => 'Ignore',
    ];

    protected $fillable = [
        'survey_id',
        'position',
        'name',
        'type',
    ];

    protected $casts = [
        'position' => 'integer',
    ];

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }
}
