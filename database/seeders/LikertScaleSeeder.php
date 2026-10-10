<?php

namespace Database\Seeders;

use App\Models\LikertScale;
use Illuminate\Database\Seeder;

/**
 * The ready-made scales every StatEase installation starts with. Users can add their own in Settings.
 * Safe to run more than once: scales that already exist (same name) are skipped.
 */
class LikertScaleSeeder extends Seeder
{
    public const DEFAULT_SCALES = [
        [
            'name' => '4-point Agreement',
            'description' => 'Common in Philippine theses; no neutral option.',
            'labels' => ['Strongly Disagree', 'Disagree', 'Agree', 'Strongly Agree'],
        ],
        [
            'name' => '5-point Agreement',
            'description' => 'Classic Likert scale with a neutral middle option.',
            'labels' => ['Strongly Disagree', 'Disagree', 'Neutral', 'Agree', 'Strongly Agree'],
        ],
        [
            'name' => '7-point Agreement',
            'description' => 'Finer agreement levels.',
            'labels' => ['Strongly Disagree', 'Disagree', 'Somewhat Disagree', 'Neutral', 'Somewhat Agree', 'Agree', 'Strongly Agree'],
        ],
        [
            'name' => '5-point Satisfaction',
            'description' => 'For service and system evaluation surveys.',
            'labels' => ['Very Dissatisfied', 'Dissatisfied', 'Neutral', 'Satisfied', 'Very Satisfied'],
        ],
        [
            'name' => '5-point Frequency',
            'description' => 'How often something happens.',
            'labels' => ['Never', 'Rarely', 'Sometimes', 'Often', 'Always'],
        ],
        [
            'name' => '4-point Acceptability (ISO 25010)',
            'description' => 'Used for software evaluation in capstone and thesis projects.',
            'labels' => ['Poor', 'Fair', 'Good', 'Excellent'],
            'interpretations' => ['Not Acceptable', 'Slightly Acceptable', 'Acceptable', 'Highly Acceptable'],
        ],
    ];

    public function run(): void
    {
        foreach (self::DEFAULT_SCALES as $definition) {
            if (LikertScale::where('name', $definition['name'])->exists()) {
                continue;
            }

            $scale = LikertScale::create([
                'name' => $definition['name'],
                'description' => $definition['description'],
                'points' => count($definition['labels']),
            ]);

            foreach ($definition['labels'] as $index => $label) {
                $scale->levels()->create([
                    'value' => $index + 1,
                    'label' => $label,
                    'interpretation' => $definition['interpretations'][$index] ?? null,
                ]);
            }
        }
    }
}
