<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Skill\Evaluation;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Skill\Evaluation\AssertionResult;
use Symfony\AI\Agent\Skill\Evaluation\EvalCase;
use Symfony\AI\Agent\Skill\Evaluation\EvalRunResult;
use Symfony\AI\Agent\Skill\Evaluation\GradingResult;
use Symfony\AI\Agent\Skill\Evaluation\TimingResult;

final class EvalRunResultTest extends TestCase
{
    public function testConstruction()
    {
        $evalCase = new EvalCase(1, 'prompt', 'expected');
        $timing = new TimingResult(100, 500);

        $result = new EvalRunResult($evalCase, 'output text', $timing);

        $this->assertSame($evalCase, $result->getEvalCase());
        $this->assertSame('output text', $result->getOutput());
        $this->assertSame($timing, $result->getTiming());
        $this->assertNull($result->getGrading());
    }

    public function testWithGradingReturnsNewInstance()
    {
        $evalCase = new EvalCase(1, 'prompt', 'expected');
        $timing = new TimingResult(100, 500);

        $result = new EvalRunResult($evalCase, 'output text', $timing);

        $grading = new GradingResult([new AssertionResult('test', true, 'evidence')]);
        $withGrading = $result->withGrading($grading);

        $this->assertNull($result->getGrading());
        $this->assertNotNull($withGrading->getGrading());
        $this->assertSame($grading, $withGrading->getGrading());
        $this->assertSame('output text', $withGrading->getOutput());
    }
}
