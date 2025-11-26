<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Tests\Bridge\Postgres;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Store\Bridge\Postgres\ReciprocalRankFusion;

final class ReciprocalRankFusionTest extends TestCase
{
    public function testDefaultConstruction()
    {
        $rrf = new ReciprocalRankFusion();

        $this->assertSame(60, $rrf->getK());
        $this->assertTrue($rrf->isNormalized());
    }

    public function testCustomConstruction()
    {
        $rrf = new ReciprocalRankFusion(k: 100, normalizeScores: false);

        $this->assertSame(100, $rrf->getK());
        $this->assertFalse($rrf->isNormalized());
    }

    public function testCalculateSingleRanking()
    {
        $rrf = new ReciprocalRankFusion(k: 60, normalizeScores: false);

        $score = $rrf->calculate([
            'vector' => ['rank' => 1, 'score' => 1.0, 'weight' => 1.0],
        ]);

        // 1/(60+1) * 1.0 * 1.0 = 0.01639...
        $this->assertEqualsWithDelta(1 / 61, $score, 0.0001);
    }

    public function testCalculateMultipleRankings()
    {
        $rrf = new ReciprocalRankFusion(k: 60, normalizeScores: false);

        $score = $rrf->calculate([
            'vector' => ['rank' => 1, 'score' => 1.0, 'weight' => 0.5],
            'fts' => ['rank' => 2, 'score' => 0.8, 'weight' => 0.5],
        ]);

        // (1/(60+1) * 1.0 * 0.5) + (1/(60+2) * 0.8 * 0.5)
        $expected = (1 / 61 * 1.0 * 0.5) + (1 / 62 * 0.8 * 0.5);
        $this->assertEqualsWithDelta($expected, $score, 0.0001);
    }

    public function testCalculateSkipsNullRank()
    {
        $rrf = new ReciprocalRankFusion(k: 60, normalizeScores: false);

        $score = $rrf->calculate([
            'vector' => ['rank' => 1, 'score' => 1.0, 'weight' => 0.5],
            'fts' => ['rank' => null, 'score' => 0.8, 'weight' => 0.5],
        ]);

        // Only vector contribution
        $expected = 1 / 61 * 1.0 * 0.5;
        $this->assertEqualsWithDelta($expected, $score, 0.0001);
    }

    public function testCalculateWithNormalization()
    {
        $rrf = new ReciprocalRankFusion(k: 60, normalizeScores: true);

        $score = $rrf->calculate([
            'vector' => ['rank' => 1, 'score' => 1.0, 'weight' => 1.0],
        ]);

        // Should be normalized to ~100 (since rank=1 with full score/weight gives max RRF)
        $this->assertEqualsWithDelta(100.0, $score, 0.01);
    }

    public function testCalculateContribution()
    {
        $rrf = new ReciprocalRankFusion(k: 60, normalizeScores: false);

        $contribution = $rrf->calculateContribution(rank: 1, score: 1.0, weight: 0.5);

        $expected = (1 / 61) * 1.0 * 0.5;
        $this->assertEqualsWithDelta($expected, $contribution, 0.0001);
    }

    public function testNormalize()
    {
        $rrf = new ReciprocalRankFusion(k: 60);

        $maxRawScore = 1 / 61; // Theoretical maximum
        $normalized = $rrf->normalize($maxRawScore);

        $this->assertEqualsWithDelta(100.0, $normalized, 0.01);
    }

    public function testDenormalize()
    {
        $rrf = new ReciprocalRankFusion(k: 60);

        $denormalized = $rrf->denormalize(100.0);

        $this->assertEqualsWithDelta(1 / 61, $denormalized, 0.0001);
    }

    public function testNormalizeAndDenormalizeAreInverse()
    {
        $rrf = new ReciprocalRankFusion(k: 60);

        $original = 0.008;
        $normalized = $rrf->normalize($original);
        $denormalized = $rrf->denormalize($normalized);

        $this->assertEqualsWithDelta($original, $denormalized, 0.0001);
    }

    public function testBuildSqlExpression()
    {
        $rrf = new ReciprocalRankFusion(k: 60);

        $sql = $rrf->buildSqlExpression(
            rankColumn: 'v.rank_ix',
            scoreExpr: '(1.0 - v.distance)',
            weight: 0.7
        );

        $this->assertStringContainsString('COALESCE(1.0 / (60 + v.rank_ix)', $sql);
        $this->assertStringContainsString('(1.0 - v.distance)', $sql);
        $this->assertStringContainsString('0.700000', $sql);
        $this->assertStringContainsString(', 0.0)', $sql);
    }

    public function testBuildSqlExpressionWithCustomNullDefault()
    {
        $rrf = new ReciprocalRankFusion(k: 60);

        $sql = $rrf->buildSqlExpression(
            rankColumn: 'rank',
            scoreExpr: 'score',
            weight: 1.0,
            nullDefault: '-1.0'
        );

        $this->assertStringContainsString(', -1.0)', $sql);
    }

    public function testBuildCombinedSqlExpression()
    {
        $rrf = new ReciprocalRankFusion(k: 60);

        $sql = $rrf->buildCombinedSqlExpression([
            ['rank_column' => 'v.rank', 'score_expr' => 'v.score', 'weight' => 0.5],
            ['rank_column' => 'f.rank', 'score_expr' => 'f.score', 'weight' => 0.5],
        ]);

        $this->assertStringContainsString('(', $sql);
        $this->assertStringContainsString(' + ', $sql);
        $this->assertStringContainsString('60 + v.rank', $sql);
        $this->assertStringContainsString('60 + f.rank', $sql);
    }

    public function testDifferentKValues()
    {
        $rrf60 = new ReciprocalRankFusion(k: 60, normalizeScores: false);
        $rrf100 = new ReciprocalRankFusion(k: 100, normalizeScores: false);

        $rankings = [
            'vector' => ['rank' => 1, 'score' => 1.0, 'weight' => 1.0],
        ];

        $score60 = $rrf60->calculate($rankings);
        $score100 = $rrf100->calculate($rankings);

        // Higher k means lower individual contributions
        $this->assertGreaterThan($score100, $score60);

        // Verify exact values
        $this->assertEqualsWithDelta(1 / 61, $score60, 0.0001);
        $this->assertEqualsWithDelta(1 / 101, $score100, 0.0001);
    }

    public function testWeightAffectsScore()
    {
        $rrf = new ReciprocalRankFusion(k: 60, normalizeScores: false);

        $scoreFullWeight = $rrf->calculate([
            'vector' => ['rank' => 1, 'score' => 1.0, 'weight' => 1.0],
        ]);

        $scoreHalfWeight = $rrf->calculate([
            'vector' => ['rank' => 1, 'score' => 1.0, 'weight' => 0.5],
        ]);

        $this->assertEqualsWithDelta($scoreFullWeight / 2, $scoreHalfWeight, 0.0001);
    }

    public function testLowerRankGivesLowerScore()
    {
        $rrf = new ReciprocalRankFusion(k: 60, normalizeScores: false);

        $scoreRank1 = $rrf->calculate([
            'vector' => ['rank' => 1, 'score' => 1.0, 'weight' => 1.0],
        ]);

        $scoreRank10 = $rrf->calculate([
            'vector' => ['rank' => 10, 'score' => 1.0, 'weight' => 1.0],
        ]);

        $this->assertGreaterThan($scoreRank10, $scoreRank1);
    }
}
