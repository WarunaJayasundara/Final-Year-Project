<?php

namespace App\Services\Analytics;

use App\Models\User;

/**
 * Standardized "deviation IQ" estimate on the classic mean-100/SD-15 scale,
 * now derived directly from the student's Rasch-model ability estimate
 * (theta) rather than a cohort mean/SD comparison: IQ = 100 + 15*theta. This
 * is the standard way IRT-based ability estimates are reported on a
 * deviation-IQ scale (theta is already approximately standard-normal after
 * PROX calibration, so this is just a linear rescaling, not a new model) -
 * and unlike the platform's cohort size, it's meaningful from the very first
 * student, since it doesn't depend on comparing against other test-takers.
 */
class IqScoreService
{
    private const MEAN_IQ = 100;

    private const SD_IQ = 15;

    private const MIN_IQ = 40;

    private const MAX_IQ = 160;

    public function estimateFor(User $user): ?array
    {
        if ($user->theta_estimate === null) {
            return null;
        }

        $iqScore = self::fromTheta((float) $user->theta_estimate);

        return [
            'iq_score' => $iqScore,
            'classification' => self::classify($iqScore),
            'method' => 'irt_theta',
            'theta' => (float) $user->theta_estimate,
            'theta_se' => $user->theta_se !== null ? (float) $user->theta_se : null,
        ];
    }

    /**
     * Shared theta->IQ rescaling, exposed statically so other services (e.g.
     * FeatureExtractionService deriving a "placement_iq" feature from a past
     * session's theta rather than the user's current one) don't duplicate the
     * formula/clamp constants.
     */
    public static function fromTheta(float $theta): int
    {
        $iq = self::MEAN_IQ + $theta * self::SD_IQ;

        return (int) round(max(self::MIN_IQ, min(self::MAX_IQ, $iq)));
    }

    /**
     * Standard deviation-IQ classification bands (Wechsler-style), applied
     * only to the final rounded IQ estimate - never derived independently
     * from raw score/percentage-correct, so this is the single place the
     * gifted/above-average/average/below-average/extremely-low labels are
     * decided. Returns a stable code (not display text) so the frontend can
     * localize it via i18n rather than the backend hardcoding English.
     */
    public static function classify(int $iqScore): string
    {
        return match (true) {
            $iqScore >= 130 => 'gifted',
            $iqScore >= 115 => 'above_average',
            $iqScore >= 85 => 'average',
            $iqScore >= 70 => 'below_average',
            default => 'extremely_low',
        };
    }
}
