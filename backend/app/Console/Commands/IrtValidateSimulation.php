<?php

namespace App\Console\Commands;

use App\Services\Irt\RaschMath;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Monte Carlo parameter-recovery study for the Rasch/PROX calibration and MLE
 * ability estimation implemented in App\Services\Irt\RaschMath. This is a
 * standard IRT validation method (see e.g. Harwell, Stone, Hsu & Kirisci
 * (1996) "Monte Carlo studies in item response theory") used precisely
 * because the platform's real usage data is still too thin to validate
 * calibration against - here we generate data with a *known* ground truth
 * (true item difficulties and true person abilities), run it through exactly
 * the same code path the live app uses, and measure how well the true
 * parameters are recovered. This is independent of question content: it
 * validates the statistical engine itself, not any particular question bank.
 *
 * Does not touch the application database - entirely self-contained.
 */
class IrtValidateSimulation extends Command
{
    protected $signature = 'irt:validate-simulation
        {--items=60 : Number of simulated items}
        {--persons=500 : Number of simulated respondents}
        {--answer-rate=0.5 : Fraction of items each simulated person answers (sparse matrix, like real usage)}
        {--seed=42 : Random seed, for a reproducible report}';

    protected $description = 'Run a Monte Carlo simulation validating Rasch calibration and ability estimation against known ground truth';

    public function handle()
    {
        $itemCount = (int) $this->option('items');
        $personCount = (int) $this->option('persons');
        $answerRate = (float) $this->option('answer-rate');
        $seed = (int) $this->option('seed');

        mt_srand($seed);

        $this->info("Simulating {$personCount} respondents answering ~".round($answerRate * 100)."% of {$itemCount} items each...");

        $trueDifficulty = [];
        foreach (range(1, $itemCount) as $i) {
            $trueDifficulty["item_{$i}"] = round($this->randomNormal(), 4);
        }

        $trueAbility = [];
        foreach (range(1, $personCount) as $p) {
            $trueAbility["person_{$p}"] = round($this->randomNormal(), 4);
        }

        $responses = [];
        foreach ($trueAbility as $person => $theta) {
            foreach ($trueDifficulty as $item => $b) {
                if ((mt_rand() / mt_getrandmax()) > $answerRate) {
                    continue; // this item wasn't in this simulated respondent's sampled set
                }

                $p = RaschMath::probabilityCorrect($theta, $b);
                $responses[] = [
                    'person' => $person,
                    'item' => $item,
                    'correct' => (mt_rand() / mt_getrandmax()) < $p,
                ];
            }
        }

        $this->info('Responses simulated: '.count($responses).'. Running PROX calibration...');

        $calibration = RaschMath::calibrateItems($responses);
        $recoveredDifficulty = $calibration['item_difficulty'];

        // Rasch/IRT item difficulty is only identified up to an additive constant
        // (a well-known property of the model - see Lord 1980, ch.2) - so before
        // comparing recovered to true parameters we mean-equate them, which is the
        // standard correction used in IRT parameter-recovery studies.
        $offset = $this->meanOf($trueDifficulty) - $this->meanOf($recoveredDifficulty);
        $equatedDifficulty = array_map(fn ($b) => $b + $offset, $recoveredDifficulty);

        $itemCorrelation = $this->pearsonCorrelation($trueDifficulty, $equatedDifficulty);
        $itemRmse = $this->rmse($trueDifficulty, $equatedDifficulty);

        $this->info('Estimating each simulated respondent\'s ability from their responses using the recovered item difficulties...');

        $responsesByPerson = [];
        foreach ($responses as $r) {
            $responsesByPerson[$r['person']][$r['item']] = $r['correct'];
        }

        $recoveredAbility = [];
        $itemsUsedCounts = [];
        foreach ($responsesByPerson as $person => $personResponses) {
            $estimate = RaschMath::estimateAbility($equatedDifficulty, $personResponses);
            $recoveredAbility[$person] = $estimate['theta'];
            $itemsUsedCounts[] = $estimate['items_used'];
        }

        $abilityCorrelation = $this->pearsonCorrelation($trueAbility, $recoveredAbility);
        $abilityRmse = $this->rmse($trueAbility, $recoveredAbility);

        $rows = [
            ['Simulated items', $itemCount],
            ['Simulated respondents', $personCount],
            ['Total simulated responses', count($responses)],
            ['Avg items answered per respondent', round(array_sum($itemsUsedCounts) / max(count($itemsUsedCounts), 1), 1)],
            ['Item difficulty recovery: Pearson r', round($itemCorrelation, 4)],
            ['Item difficulty recovery: RMSE (logits)', round($itemRmse, 4)],
            ['Person ability recovery: Pearson r', round($abilityCorrelation, 4)],
            ['Person ability recovery: RMSE (logits)', round($abilityRmse, 4)],
        ];

        $this->table(['Metric', 'Value'], $rows);

        $this->writeReport($rows, $itemCount, $personCount, $answerRate, $seed);

        return Command::SUCCESS;
    }

    private function writeReport(array $rows, int $itemCount, int $personCount, float $answerRate, int $seed): void
    {
        $lines = [];
        $lines[] = '# IRT Calibration Monte Carlo Validation Report';
        $lines[] = '';
        $lines[] = 'Generated: '.now()->toDateTimeString();
        $lines[] = '';
        $lines[] = '## Method';
        $lines[] = '';
        $lines[] = "A synthetic dataset of {$personCount} respondents with known ability (theta ~ N(0,1)) answering a sparse ".
            "random subset (~".round($answerRate * 100)."% each) of {$itemCount} items with known difficulty (b ~ N(0,1)) was ".
            'generated per the Rasch model: P(correct) = 1 / (1 + e^-(theta - b)). This simulated response data was then run '.
            'through the same PROX (Wright & Stone, 1979) joint calibration and Newton-Raphson MLE ability estimation code '.
            "used by the live application (App\\Services\\Irt\\RaschMath), with a fixed random seed ({$seed}) for reproducibility. ".
            'Recovered item difficulties were mean-equated against the true difficulties before comparison, since Rasch-model '.
            'difficulty is only identified up to an additive constant (Lord, 1980).';
        $lines[] = '';
        $lines[] = '## Results';
        $lines[] = '';
        $lines[] = '| Metric | Value |';
        $lines[] = '|---|---|';
        foreach ($rows as [$metric, $value]) {
            $lines[] = "| {$metric} | {$value} |";
        }
        $lines[] = '';
        $lines[] = '## Interpretation';
        $lines[] = '';
        $lines[] = 'A Pearson correlation close to 1.0 between true and recovered parameters indicates the calibration and '.
            'ability-estimation procedures correctly recover the underlying latent trait structure; RMSE quantifies the '.
            'typical magnitude of estimation error on the logit scale. These values validate the statistical engine in '.
            "isolation from any particular question bank's content.";

        $path = 'irt_simulation_report.md';
        Storage::disk('local')->put($path, implode("\n", $lines));

        $this->info('Report written to: '.Storage::disk('local')->path($path));
    }

    /** Standard normal deviate via Box-Muller transform. */
    private function randomNormal(): float
    {
        $u1 = mt_rand(1, mt_getrandmax() - 1) / mt_getrandmax();
        $u2 = mt_rand(1, mt_getrandmax() - 1) / mt_getrandmax();

        return sqrt(-2 * log($u1)) * cos(2 * M_PI * $u2);
    }

    /** @param array<string,float> $values */
    private function meanOf(array $values): float
    {
        return count($values) > 0 ? array_sum($values) / count($values) : 0.0;
    }

    /**
     * Pearson correlation between two parallel arrays keyed the same way.
     *
     * @param  array<string,float>  $a
     * @param  array<string,float>  $b
     */
    private function pearsonCorrelation(array $a, array $b): float
    {
        $keys = array_intersect(array_keys($a), array_keys($b));
        $n = count($keys);
        if ($n < 2) {
            return 0.0;
        }

        $meanA = $this->meanOf(array_intersect_key($a, array_flip($keys)));
        $meanB = $this->meanOf(array_intersect_key($b, array_flip($keys)));

        $numerator = 0.0;
        $sumSqA = 0.0;
        $sumSqB = 0.0;

        foreach ($keys as $key) {
            $da = $a[$key] - $meanA;
            $db = $b[$key] - $meanB;
            $numerator += $da * $db;
            $sumSqA += $da ** 2;
            $sumSqB += $db ** 2;
        }

        $denominator = sqrt($sumSqA * $sumSqB);

        return $denominator > 0 ? $numerator / $denominator : 0.0;
    }

    /**
     * @param  array<string,float>  $a
     * @param  array<string,float>  $b
     */
    private function rmse(array $a, array $b): float
    {
        $keys = array_intersect(array_keys($a), array_keys($b));
        $n = count($keys);
        if ($n === 0) {
            return 0.0;
        }

        $sumSquares = 0.0;
        foreach ($keys as $key) {
            $sumSquares += ($a[$key] - $b[$key]) ** 2;
        }

        return sqrt($sumSquares / $n);
    }
}
