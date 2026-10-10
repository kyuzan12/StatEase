<?php

namespace App\Models;

use App\Support\DivergingPalette;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LikertScale extends Model
{
    public const MIN_POINTS = 2;
    public const MAX_POINTS = 10;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'points',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'points' => 'integer',
    ];

    /** @var array<string, int>|null  label (lowercase) => value, built once per request */
    private ?array $labelMap = null;

    /**
     * The scales a user may use: the built-in ones (no owner) and their own.
     * Usage: LikertScale::visibleTo($user)->get()
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $user->id));
    }

    /**
     * Built-in scales are shared by every user, so nobody may edit or delete them.
     */
    public function isBuiltIn(): bool
    {
        return $this->user_id === null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The levels from lowest (1) to highest.
     */
    public function levels(): HasMany
    {
        return $this->hasMany(LikertScaleLevel::class)->orderBy('value');
    }

    public function surveys(): HasMany
    {
        return $this->hasMany(Survey::class);
    }

    /**
     * Convert one answer to its numeric value, or null if it is not on this scale.
     * Accepts the number ("4") or the label ("Strongly Agree", in any letter case).
     */
    public function valueFor(string $answer): ?int
    {
        $answer = trim($answer);

        if (ctype_digit($answer)) {
            $value = (int) $answer;

            return $value >= 1 && $value <= $this->points ? $value : null;
        }

        if ($this->labelMap === null) {
            $this->labelMap = [];
            foreach ($this->levels as $level) {
                $this->labelMap[$this->normalize($level->label)] = $level->value;
            }
        }

        return $this->labelMap[$this->normalize($answer)] ?? null;
    }

    /**
     * Background and text colors per level: red (low) -> gray (middle) -> blue (high).
     *
     * @return array<int, array{background: string, text: string}>  keyed by level value
     */
    public function colors(): array
    {
        return DivergingPalette::forPoints($this->points);
    }

    /**
     * "Strongly  agree " -> "strongly agree", so small typing differences still match.
     */
    private function normalize(string $text): string
    {
        return mb_strtolower(preg_replace('/\s+/', ' ', trim($text)));
    }
}
