<?php

namespace App\Support;

/**
 * P-values for the chi-square, F and t distributions, written from the standard formulas
 * (regularized incomplete gamma and beta functions, as in "Numerical Recipes").
 *
 * A p-value is the probability of getting a result at least this extreme if the null hypothesis
 * (no relationship / no difference) were true. Small p (usually < 0.05) = significant.
 */
class Distributions
{
    private const EPSILON = 1e-14;
    private const MAX_ITERATIONS = 500;

    /**
     * P(X >= x) for a chi-square distribution with df degrees of freedom.
     */
    public static function chiSquarePValue(float $x, int $df): float
    {
        if ($x <= 0) {
            return 1.0;
        }

        return self::clamp(1 - self::regularizedGammaP($df / 2, $x / 2));
    }

    /**
     * P(F >= f) for an F distribution with (df1, df2) degrees of freedom.
     */
    public static function fPValue(float $f, int $df1, int $df2): float
    {
        if ($f <= 0) {
            return 1.0;
        }

        return self::clamp(self::regularizedBeta($df2 / ($df2 + $df1 * $f), $df2 / 2, $df1 / 2));
    }

    /**
     * Two-tailed P(|T| >= |t|) for a t distribution with df degrees of freedom.
     */
    public static function tTwoTailedPValue(float $t, int $df): float
    {
        return self::clamp(self::regularizedBeta($df / ($df + $t * $t), $df / 2, 0.5));
    }

    /**
     * ln(Γ(x)) using the Lanczos approximation.
     */
    public static function logGamma(float $x): float
    {
        $coefficients = [76.18009172947146, -86.50532032941677, 24.01409824083091,
            -1.231739572450155, 0.1208650973866179e-2, -0.5395239384953e-5];

        $y = $x;
        $tmp = $x + 5.5;
        $tmp -= ($x + 0.5) * log($tmp);
        $series = 1.000000000190015;
        foreach ($coefficients as $c) {
            $series += $c / ++$y;
        }

        return -$tmp + log(2.5066282746310005 * $series / $x);
    }

    /**
     * Regularized lower incomplete gamma function P(a, x).
     */
    private static function regularizedGammaP(float $a, float $x): float
    {
        if ($x < $a + 1) {
            // Series expansion
            $term = $sum = 1 / $a;
            for ($n = 1; $n <= self::MAX_ITERATIONS; $n++) {
                $term *= $x / ($a + $n);
                $sum += $term;
                if (abs($term) < abs($sum) * self::EPSILON) {
                    break;
                }
            }

            return $sum * exp(-$x + $a * log($x) - self::logGamma($a));
        }

        // Continued fraction for Q(a, x), then P = 1 - Q
        $b = $x + 1 - $a;
        $c = 1 / 1e-300;
        $d = 1 / $b;
        $h = $d;
        for ($i = 1; $i <= self::MAX_ITERATIONS; $i++) {
            $an = -$i * ($i - $a);
            $b += 2;
            $d = $an * $d + $b;
            $d = abs($d) < 1e-300 ? 1e-300 : $d;
            $c = $b + $an / $c;
            $c = abs($c) < 1e-300 ? 1e-300 : $c;
            $d = 1 / $d;
            $delta = $d * $c;
            $h *= $delta;
            if (abs($delta - 1) < self::EPSILON) {
                break;
            }
        }

        return 1 - exp(-$x + $a * log($x) - self::logGamma($a)) * $h;
    }

    /**
     * Regularized incomplete beta function I_x(a, b).
     */
    private static function regularizedBeta(float $x, float $a, float $b): float
    {
        if ($x <= 0) {
            return 0.0;
        }
        if ($x >= 1) {
            return 1.0;
        }

        $front = exp(self::logGamma($a + $b) - self::logGamma($a) - self::logGamma($b) + $a * log($x) + $b * log(1 - $x));

        // The continued fraction converges fastest on this side; otherwise use the symmetry I_x(a,b) = 1 - I_(1-x)(b,a).
        if ($x < ($a + 1) / ($a + $b + 2)) {
            return $front * self::betaContinuedFraction($x, $a, $b) / $a;
        }

        return 1 - $front * self::betaContinuedFraction(1 - $x, $b, $a) / $b;
    }

    private static function betaContinuedFraction(float $x, float $a, float $b): float
    {
        $qab = $a + $b;
        $qap = $a + 1;
        $qam = $a - 1;
        $c = 1.0;
        $d = 1 - $qab * $x / $qap;
        $d = abs($d) < 1e-300 ? 1e-300 : $d;
        $d = 1 / $d;
        $h = $d;

        for ($m = 1; $m <= self::MAX_ITERATIONS; $m++) {
            $m2 = 2 * $m;

            $aa = $m * ($b - $m) * $x / (($qam + $m2) * ($a + $m2));
            $d = 1 + $aa * $d;
            $d = abs($d) < 1e-300 ? 1e-300 : $d;
            $c = 1 + $aa / $c;
            $c = abs($c) < 1e-300 ? 1e-300 : $c;
            $d = 1 / $d;
            $h *= $d * $c;

            $aa = -($a + $m) * ($qab + $m) * $x / (($a + $m2) * ($qap + $m2));
            $d = 1 + $aa * $d;
            $d = abs($d) < 1e-300 ? 1e-300 : $d;
            $c = 1 + $aa / $c;
            $c = abs($c) < 1e-300 ? 1e-300 : $c;
            $d = 1 / $d;
            $delta = $d * $c;
            $h *= $delta;

            if (abs($delta - 1) < self::EPSILON) {
                break;
            }
        }

        return $h;
    }

    private static function clamp(float $p): float
    {
        return max(0.0, min(1.0, $p));
    }
}
