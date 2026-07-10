<?php

namespace App\Services\Analytics;

use App\Models\Question;
use App\Models\SessionAnswer;
use App\Models\User;

/**
 * Psychometric validation of the live item bank and the platform's ability
 * estimates - what turns "2000+ questions" into an empirically checked
 * measurement instrument rather than just a pile of content. Backs the admin
 * "Psychometrics" dashboard.
 *
 * Reliability is reported as *marginal reliability* (Green, Bock, Humphreys,
 * Linn & Reckase, 1984) rather than classical Cronbach's alpha, because
 * students each answer a different, adaptively/randomly sampled set of
 * items - alpha assumes a common fixed test form, which doesn't hold here.
 * Marginal reliability = 1 - mean(SE(theta)^2) / Var(theta) is the standard
 * IRT/CAT analogue and uses exactly the per-student standard errors this
 * platform already computes.
 */
class ItemAnalysisService
{
    public function summary(): array
    {
        $totalItems = Question::where('is_active', true)->count();
        $calibratedItems = Question::where('is_active', true)->whereNotNull('irt_difficulty')->count();

        $users = User::whereNotNull('theta_estimate')->get(['theta_estimate', 'theta_se']);
        $cohortSize = $users->count();

        $thetaMean = null;
        $thetaSd = null;
        $meanSe = null;
        $marginalReliability = null;

        if ($cohortSize > 0) {
            $thetaMean = (float) $users->avg('theta_estimate');
            $variance = $users->reduce(
                fn ($carry, User $u) => $carry + ($u->theta_estimate - $thetaMean) ** 2,
                0.0
            ) / $cohortSize;
            $thetaSd = sqrt($variance);

            $withSe = $users->whereNotNull('theta_se');
            if ($withSe->count() > 0) {
                $meanSe = (float) $withSe->avg('theta_se');
                if ($variance > 0) {
                    $marginalReliability = round(max(0.0, 1 - ($meanSe ** 2) / $variance), 4);
                }
            }
        }

        return [
            'total_items' => $totalItems,
            'calibrated_items' => $calibratedItems,
            'cohort_size' => $cohortSize,
            'theta_mean' => $thetaMean !== null ? round($thetaMean, 3) : null,
            'theta_sd' => $thetaSd !== null ? round($thetaSd, 3) : null,
            'mean_se' => $meanSe !== null ? round($meanSe, 3) : null,
            'marginal_reliability' => $marginalReliability,
        ];
    }

    public function categoryDifficulty(): array
    {
        return Question::where('questions.is_active', true)
            ->whereNotNull('irt_difficulty')
            ->join('categories', 'categories.id', '=', 'questions.category_id')
            ->selectRaw('categories.name_en, categories.name_si, count(*) as calibrated_count, '.
                'avg(questions.irt_difficulty) as mean_difficulty, '.
                'min(questions.irt_difficulty) as min_difficulty, '.
                'max(questions.irt_difficulty) as max_difficulty')
            ->groupBy('categories.id', 'categories.name_en', 'categories.name_si')
            ->get()
            ->map(fn ($row) => [
                'name_en' => $row->name_en,
                'name_si' => $row->name_si,
                'calibrated_count' => (int) $row->calibrated_count,
                'mean_difficulty' => round((float) $row->mean_difficulty, 3),
                'min_difficulty' => round((float) $row->min_difficulty, 3),
                'max_difficulty' => round((float) $row->max_difficulty, 3),
            ])
            ->values()
            ->all();
    }

    /**
     * Point-biserial item discrimination: correlation between getting item i
     * right/wrong and overall ability (theta at time of analysis). Requires
     * at least 5 responses to an item, from respondents with both a correct
     * and an incorrect outcome present, to be numerically meaningful.
     */
    public function itemDiscrimination(int $limit = 10): array
    {
        $rows = SessionAnswer::whereNotNull('session_answers.answered_at')
            ->join('questions', 'questions.id', '=', 'session_answers.question_id')
            ->join('test_sessions', 'test_sessions.id', '=', 'session_answers.test_session_id')
            ->join('users', 'users.id', '=', 'test_sessions.user_id')
            ->whereNotNull('users.theta_estimate')
            ->select(
                'questions.id as question_id',
                'questions.question_text_en',
                'session_answers.is_correct',
                'users.theta_estimate'
            )
            ->get();

        $results = [];

        foreach ($rows->groupBy('question_id') as $questionId => $group) {
            if ($group->count() < 5) {
                continue;
            }

            $correctThetas = $group->where('is_correct', true)->pluck('theta_estimate');
            $incorrectThetas = $group->where('is_correct', false)->pluck('theta_estimate');

            if ($correctThetas->isEmpty() || $incorrectThetas->isEmpty()) {
                continue;
            }

            $p = $correctThetas->count() / $group->count();
            $sd = $this->stdDev($group->pluck('theta_estimate')->all());

            if ($sd <= 0) {
                continue;
            }

            $discrimination = (($correctThetas->avg() - $incorrectThetas->avg()) / $sd) * sqrt($p * (1 - $p));

            $results[] = [
                'question_id' => $questionId,
                'question_text_en' => $group->first()->question_text_en,
                'responses' => $group->count(),
                'discrimination' => round($discrimination, 3),
            ];
        }

        usort($results, fn ($a, $b) => $b['discrimination'] <=> $a['discrimination']);

        return [
            'top' => array_slice($results, 0, $limit),
            'bottom' => array_slice(array_reverse($results), 0, $limit),
            'items_analyzed' => count($results),
        ];
    }

    /** @param float[] $values */
    private function stdDev(array $values): float
    {
        $n = count($values);
        if ($n === 0) {
            return 0.0;
        }

        $mean = array_sum($values) / $n;
        $variance = array_sum(array_map(fn ($v) => ($v - $mean) ** 2, $values)) / $n;

        return sqrt($variance);
    }
}
