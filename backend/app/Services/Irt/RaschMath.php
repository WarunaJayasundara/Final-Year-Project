<?php

namespace App\Services\Irt;

/**
 * Pure, framework-free implementation of the two core Rasch-model (1PL Item
 * Response Theory) computations used by this platform's adaptive testing
 * engine. Kept independent of Eloquent/the database on purpose so the exact
 * same code path can be:
 *  - run against real response data (via RaschCalibrationService / AbilityEstimationService), and
 *  - run against a synthetic dataset with a known ground truth (via the
 *    "php artisan irt:validate-simulation" Monte Carlo recovery study),
 * which is what lets that simulation genuinely validate this code rather
 * than a re-implementation of it.
 *
 * References:
 *  - Rasch, G. (1960). Probabilistic Models for Some Intelligence and Attainment Tests.
 *  - Wright, B.D. & Stone, M.H. (1979). Best Test Design. Chicago: MESA Press.
 *    (source of the PROX / "Normal Approximation" joint calibration algorithm below)
 *  - Lord, F.M. (1980). Applications of Item Response Theory to Practical Testing Problems.
 */
class RaschMath
{
    /** Continuity-correction bound so proportions of 0 or 1 never produce an infinite logit. */
    private const PROX_VARIANCE_CONSTANT = 2.89; // (pi^2/3) / (pi^2/3 + 1) normalising constant used by PROX, per Wright & Stone (1979)

    /**
     * PROX (Normal Approximation) joint calibration: recovers item difficulty
     * (and, incidentally, person ability) from a response matrix in one closed-form
     * pass - no iteration needed, unlike full joint maximum likelihood estimation.
     * Works on sparse data (not every person needs to answer every item), which is
     * essential here since each student only ever sees a sampled subset of the bank.
     *
     * @param  array<int,array{person: int|string, item: int|string, correct: bool}>  $responses
     * @return array{item_difficulty: array<int|string,float>, person_ability: array<int|string,float>}
     */
    public static function calibrateItems(array $responses): array
    {
        $itemTotals = [];
        $itemCorrect = [];
        $personTotals = [];
        $personCorrect = [];

        foreach ($responses as $r) {
            $item = $r['item'];
            $person = $r['person'];
            $correct = $r['correct'] ? 1 : 0;

            $itemTotals[$item] = ($itemTotals[$item] ?? 0) + 1;
            $itemCorrect[$item] = ($itemCorrect[$item] ?? 0) + $correct;
            $personTotals[$person] = ($personTotals[$person] ?? 0) + 1;
            $personCorrect[$person] = ($personCorrect[$person] ?? 0) + $correct;
        }

        $itemLogits = [];
        foreach ($itemTotals as $item => $n) {
            $itemLogits[$item] = self::logit(self::correctedProportion($itemCorrect[$item], $n));
        }

        $personLogits = [];
        foreach ($personTotals as $person => $n) {
            $personLogits[$person] = self::logit(self::correctedProportion($personCorrect[$person], $n));
        }

        $meanItemLogit = self::mean($itemLogits);
        $meanPersonLogit = self::mean($personLogits);
        $varItemLogit = self::variance($itemLogits, $meanItemLogit);
        $varPersonLogit = self::variance($personLogits, $meanPersonLogit);

        // Expansion factors correct for the fact that logits from finite item/person
        // samples are more spread out than the underlying logistic distribution - see
        // Wright & Stone (1979) ch.4. Without this correction PROX systematically
        // under-estimates the true spread of item difficulty / person ability.
        $itemExpansion = sqrt(1 + $varItemLogit / self::PROX_VARIANCE_CONSTANT);
        $personExpansion = sqrt(1 + $varPersonLogit / self::PROX_VARIANCE_CONSTANT);

        $itemDifficulty = [];
        foreach ($itemLogits as $item => $logit) {
            $itemDifficulty[$item] = round($itemExpansion * ($meanPersonLogit - $logit), 4);
        }

        $personAbility = [];
        foreach ($personLogits as $person => $logit) {
            $personAbility[$person] = round($personExpansion * ($logit - $meanItemLogit), 4);
        }

        return ['item_difficulty' => $itemDifficulty, 'person_ability' => $personAbility];
    }

    /**
     * Maximum-likelihood ability (theta) estimation via Newton-Raphson, given a
     * fixed set of item difficulties. This is what re-estimates a student's
     * ability after every answer during an adaptive placement test, and after
     * every completed daily session using their full response history.
     *
     * @param  array<int|string,float>  $itemDifficulties  item key => difficulty (b)
     * @param  array<int|string,bool>  $responses  item key => correct? (only keys present in both arrays are used)
     * @return array{theta: float, se: float, items_used: int}
     */
    public static function estimateAbility(array $itemDifficulties, array $responses, float $startingTheta = 0.0): array
    {
        $items = array_intersect(array_keys($responses), array_keys($itemDifficulties));

        if (count($items) === 0) {
            return ['theta' => $startingTheta, 'se' => 9.99, 'items_used' => 0];
        }

        $theta = $startingTheta;

        for ($iteration = 0; $iteration < 25; $iteration++) {
            $numerator = 0.0;
            $information = 0.0;

            foreach ($items as $item) {
                $b = $itemDifficulties[$item];
                $p = self::probabilityCorrect($theta, $b);
                $u = $responses[$item] ? 1 : 0;
                $numerator += $u - $p;
                $information += $p * (1 - $p);
            }

            if ($information < 1e-6) {
                break;
            }

            $delta = $numerator / $information;
            $theta = max(-4.5, min(4.5, $theta + $delta));

            if (abs($delta) < 0.001) {
                break;
            }
        }

        $finalInformation = 0.0;
        foreach ($items as $item) {
            $p = self::probabilityCorrect($theta, $itemDifficulties[$item]);
            $finalInformation += $p * (1 - $p);
        }

        $se = $finalInformation > 1e-6 ? round(1 / sqrt($finalInformation), 4) : 9.99;

        return ['theta' => round($theta, 4), 'se' => $se, 'items_used' => count($items)];
    }

    /** Rasch/1PL probability of a correct response: P(theta) = 1 / (1 + e^-(theta - b)). */
    public static function probabilityCorrect(float $theta, float $difficulty): float
    {
        return 1 / (1 + exp(-($theta - $difficulty)));
    }

    /** Fisher information at theta for a single item - item selection picks the item maximizing this. */
    public static function information(float $theta, float $difficulty): float
    {
        $p = self::probabilityCorrect($theta, $difficulty);

        return $p * (1 - $p);
    }

    private static function logit(float $p): float
    {
        return log($p / (1 - $p));
    }

    /** Continuity correction so a 0% or 100% proportion never produces an infinite logit. */
    private static function correctedProportion(int $correct, int $total): float
    {
        if ($total === 0) {
            return 0.5;
        }

        $p = $correct / $total;
        $bound = 1 / (2 * $total);

        return min(max($p, $bound), 1 - $bound);
    }

    /** @param array<int|string,float> $values */
    private static function mean(array $values): float
    {
        return count($values) > 0 ? array_sum($values) / count($values) : 0.0;
    }

    /** @param array<int|string,float> $values */
    private static function variance(array $values, float $mean): float
    {
        if (count($values) === 0) {
            return 0.0;
        }

        $sumSquares = array_sum(array_map(fn ($v) => ($v - $mean) ** 2, $values));

        return $sumSquares / count($values);
    }
}
