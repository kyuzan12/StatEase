<?php

namespace Tests\Unit;

use App\Models\LikertScale;
use App\Models\LikertScaleLevel;
use App\Services\NarrativeService;
use App\Services\StatisticsService;
use App\Support\Distributions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Checks the formulas against values computed by hand or taken from statistical tables.
 * Run with: php artisan test
 */
class StatisticsTest extends TestCase
{
    /** A scale in memory only (no database needed). */
    private function scale(array $labels): LikertScale
    {
        $scale = new LikertScale(['name' => 'Test', 'points' => count($labels)]);
        $levels = collect($labels)->map(fn ($label, $i) => new LikertScaleLevel(['value' => $i + 1, 'label' => $label]));
        $scale->setRelation('levels', $levels);

        return $scale;
    }

    public function test_four_point_ranges_use_an_interval_of_0_75(): void
    {
        $ranges = (new StatisticsService)->interpretationScale($this->scale(['SD', 'D', 'A', 'SA']));

        $this->assertSame([1.0, 1.76, 2.51, 3.26], array_column($ranges, 'min'));
        $this->assertSame([1.75, 2.5, 3.25, 4.0], array_column($ranges, 'max'));
    }

    public function test_five_point_ranges_use_an_interval_of_0_80(): void
    {
        $ranges = (new StatisticsService)->interpretationScale($this->scale(['SD', 'D', 'N', 'A', 'SA']));

        $this->assertSame([1.8, 2.6, 3.4, 4.2, 5.0], array_column($ranges, 'max'));
    }

    #[DataProvider('boundaries')]
    public function test_weighted_mean_is_interpreted_at_the_range_boundaries(float $mean, string $expected): void
    {
        $statistics = new StatisticsService;
        $ranges = $statistics->interpretationScale($this->scale(['Strongly Disagree', 'Disagree', 'Agree', 'Strongly Agree']));

        $this->assertSame($expected, $statistics->interpret($mean, $ranges)['label']);
    }

    public static function boundaries(): array
    {
        return [
            '1.75 is still the lowest level' => [1.75, 'Strongly Disagree'],
            '1.76 starts the next level' => [1.76, 'Disagree'],
            '3.25 is Agree' => [3.25, 'Agree'],
            '3.26 is Strongly Agree' => [3.26, 'Strongly Agree'],
            '3.254 is shown and read as 3.25' => [3.254, 'Agree'],
        ];
    }

    public function test_scale_reads_numbers_and_labels_in_any_letter_case(): void
    {
        $scale = $this->scale(['Strongly Disagree', 'Disagree', 'Agree', 'Strongly Agree']);

        $this->assertSame(4, $scale->valueFor('4'));
        $this->assertSame(4, $scale->valueFor('  STRONGLY agree '));
        $this->assertNull($scale->valueFor('5'));        // outside a 4-point scale
        $this->assertNull($scale->valueFor('Maybe'));    // not one of the labels
    }

    #[DataProvider('slovinCases')]
    public function test_slovin_rounds_up_to_a_whole_respondent(int $population, float $error, int $expected): void
    {
        $this->assertSame($expected, (new StatisticsService)->slovin($population, $error)['sample_size']);
    }

    public static function slovinCases(): array
    {
        return [
            '1000 at 5% = 285.71 -> 286' => [1000, 0.05, 286],
            '500 at 5% = 222.22 -> 223' => [500, 0.05, 223],
            '100 at 10% = exactly 50' => [100, 0.10, 50],
        ];
    }

    #[DataProvider('tableValues')]
    public function test_p_values_match_statistical_tables(string $test, float $value, int $df1, ?int $df2, float $expected): void
    {
        $p = match ($test) {
            'chi2' => Distributions::chiSquarePValue($value, $df1),
            'F' => Distributions::fPValue($value, $df1, $df2),
            't' => Distributions::tTwoTailedPValue($value, $df1),
        };

        $this->assertEqualsWithDelta($expected, $p, 0.0005);
    }

    public static function tableValues(): array
    {
        return [
            'chi-square 3.841, df 1' => ['chi2', 3.841, 1, null, 0.05],
            'chi-square 9.488, df 4' => ['chi2', 9.488, 4, null, 0.05],
            'F 3.354, df 2 and 27' => ['F', 3.354, 2, 27, 0.05],
            'F 4.431, df 4 and 20' => ['F', 4.431, 4, 20, 0.01],
            't 2.228, df 10' => ['t', 2.228, 10, null, 0.05],
            't 4.032, df 5' => ['t', 4.032, 5, null, 0.01],
        ];
    }

    public function test_numbers_are_written_in_words_for_the_interpretation(): void
    {
        $narrative = new NarrativeService;

        $this->assertSame('fifteen', $narrative->words(15));
        $this->assertSame('twenty-one', $narrative->words(21));
        $this->assertSame('p < .001', $narrative->p(0.0002));
        $this->assertSame('p = .034', $narrative->p(0.0341));
    }
}
