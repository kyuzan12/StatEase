<?php

namespace App\Support;

/**
 * Colors for an ordered agree/disagree scale.
 *
 * A Likert scale has two "directions" (negative and positive) with an optional neutral middle,
 * so it uses a diverging palette: red shades for the low end, gray for the middle, blue shades
 * for the high end. Darker = stronger. This is the standard way to color Likert results.
 * Each side was checked to read clearly from light to dark, also for color-blind users.
 */
class DivergingPalette
{
    // From the strongest shade to the lightest (closest to neutral).
    private const RED = ['#7a1519', '#9e2226', '#c22f31', '#e34948', '#ec7f7a'];
    private const BLUE = ['#0a2d5a', '#104281', '#1c5cab', '#2a78d6', '#5598e7'];
    private const NEUTRAL = '#cfcec8';

    /**
     * @return array<int, array{background: string, text: string}>  keyed by level value (1 = lowest)
     */
    public static function forPoints(int $points): array
    {
        $perSide = intdiv($points, 2);
        $red = self::pick(self::RED, $perSide);                  // value 1 gets the darkest red
        $blue = array_reverse(self::pick(self::BLUE, $perSide)); // the highest value gets the darkest blue

        $backgrounds = $points % 2 === 1
            ? [...$red, self::NEUTRAL, ...$blue]
            : [...$red, ...$blue];

        $colors = [];
        foreach ($backgrounds as $index => $background) {
            $colors[$index + 1] = [
                'background' => $background,
                'text' => self::readableTextColor($background),
            ];
        }

        return $colors;
    }

    /**
     * Pick $count shades spread evenly from strongest to lightest.
     */
    private static function pick(array $shades, int $count): array
    {
        if ($count <= 1) {
            return [$shades[2]];
        }

        $last = count($shades) - 1;
        $picked = [];
        for ($i = 0; $i < $count; $i++) {
            $picked[] = $shades[(int) round($i * $last / ($count - 1))];
        }

        return $picked;
    }

    /**
     * White or near-black text, whichever has more contrast on this background (WCAG formula).
     */
    private static function readableTextColor(string $hex): string
    {
        $luminance = self::luminance($hex);
        $contrastWithWhite = 1.05 / ($luminance + 0.05);
        $contrastWithBlack = ($luminance + 0.05) / 0.05;

        return $contrastWithWhite >= $contrastWithBlack ? '#ffffff' : '#0b0b0b';
    }

    private static function luminance(string $hex): float
    {
        $channels = array_map(fn ($pair) => hexdec($pair) / 255, str_split(ltrim($hex, '#'), 2));
        $linear = array_map(fn ($c) => $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4, $channels);

        return 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
    }
}
