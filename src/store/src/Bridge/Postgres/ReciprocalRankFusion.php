<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Postgres;

/**
 * Reciprocal Rank Fusion (RRF) calculator for combining multiple search rankings.
 *
 * RRF is a method to combine results from multiple search algorithms by their ranks.
 * The formula is: score = Σ (weight_i / (k + rank_i))
 *
 * @see https://plg.uwaterloo.ca/~gvcormac/cormacksigir09-rrf.pdf
 *
 * @author Ahmed EBEN HASSINE <ahmedbhs123@gmail.com>
 */
final class ReciprocalRankFusion
{
    /**
     * @param int  $k               RRF constant (default: 60). Higher values give more equal weighting between results.
     * @param bool $normalizeScores Whether to normalize scores to 0-100 range (default: true)
     */
    public function __construct(
        private readonly int $k = 60,
        private readonly bool $normalizeScores = true,
    ) {
    }

    /**
     * Calculate RRF score for a single result with multiple rankings.
     *
     * @param array<string, array{rank: int|null, score: float, weight: float}> $rankings
     *                                                                                    Each entry contains: rank (1-based or null), score (normalized 0-1), weight (0-1)
     *
     * @return float The combined RRF score
     */
    public function calculate(array $rankings): float
    {
        $score = 0.0;

        foreach ($rankings as $ranking) {
            if (null === $ranking['rank']) {
                continue;
            }

            $contribution = (1.0 / ($this->k + $ranking['rank'])) * $ranking['score'] * $ranking['weight'];
            $score += $contribution;
        }

        if ($this->normalizeScores) {
            $score = $this->normalize($score);
        }

        return $score;
    }

    /**
     * Calculate individual contribution for a ranking.
     *
     * @param int   $rank   The rank (1-based position)
     * @param float $score  The normalized score (0-1)
     * @param float $weight The weight for this ranking source (0-1)
     */
    public function calculateContribution(int $rank, float $score, float $weight): float
    {
        $contribution = (1.0 / ($this->k + $rank)) * $score * $weight;

        if ($this->normalizeScores) {
            $contribution = $this->normalize($contribution);
        }

        return $contribution;
    }

    /**
     * Normalize a score to 0-100 range.
     *
     * The theoretical maximum RRF score is 1/(k+1), so we normalize against that.
     */
    public function normalize(float $score): float
    {
        $maxScore = 1.0 / ($this->k + 1);

        return ($score / $maxScore) * 100;
    }

    /**
     * Denormalize a score from 0-100 range back to raw RRF score.
     */
    public function denormalize(float $normalizedScore): float
    {
        $maxScore = 1.0 / ($this->k + 1);

        return ($normalizedScore / 100) * $maxScore;
    }

    /**
     * Build SQL expression for RRF calculation.
     *
     * @param string $rankColumn  The column containing the rank
     * @param string $scoreExpr   SQL expression for the normalized score (0-1)
     * @param float  $weight      The weight for this ranking source
     * @param string $nullDefault Default value when rank is NULL (default: '0.0')
     */
    public function buildSqlExpression(
        string $rankColumn,
        string $scoreExpr,
        float $weight,
        string $nullDefault = '0.0',
    ): string {
        return \sprintf(
            'COALESCE(1.0 / (%d + %s) * %s * %f, %s)',
            $this->k,
            $rankColumn,
            $scoreExpr,
            $weight,
            $nullDefault,
        );
    }

    /**
     * Build SQL expression for combining multiple RRF contributions.
     *
     * @param array<array{rank_column: string, score_expr: string, weight: float}> $sources
     */
    public function buildCombinedSqlExpression(array $sources): string
    {
        $expressions = [];

        foreach ($sources as $source) {
            $expressions[] = $this->buildSqlExpression(
                $source['rank_column'],
                $source['score_expr'],
                $source['weight'],
            );
        }

        return '('.implode(' + ', $expressions).')';
    }

    public function getK(): int
    {
        return $this->k;
    }

    public function isNormalized(): bool
    {
        return $this->normalizeScores;
    }
}
