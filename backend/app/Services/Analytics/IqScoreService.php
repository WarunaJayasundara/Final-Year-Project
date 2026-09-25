<?php

namespace App\Services\Analytics;

use App\Models\User;

/** Standardized "deviation IQ" estimate on the classic mean-100/SD-15 scale. */
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

    /** Shared theta->IQ rescaling, exposed statically so other services. */
    public static function fromTheta(float $theta): int
    {
        $iq = self::MEAN_IQ + $theta * self::SD_IQ;

        return (int) round(max(self::MIN_IQ, min(self::MAX_IQ, $iq)));
    }

    /** Standard deviation-IQ classification bands (Wechsler-style), applied only to the final rounded IQ estimate. */
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
