<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LikertScaleLevel extends Model
{
    protected $fillable = [
        'likert_scale_id',
        'value',
        'label',
        'interpretation',
    ];

    protected $casts = [
        'value' => 'integer',
    ];

    public function scale(): BelongsTo
    {
        return $this->belongsTo(LikertScale::class, 'likert_scale_id');
    }

    /**
     * The words used in the results. Falls back to the label when no interpretation is set.
     */
    public function displayInterpretation(): string
    {
        return $this->interpretation ?: $this->label;
    }
}
