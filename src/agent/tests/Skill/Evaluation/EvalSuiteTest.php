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
use Symfony\AI\Agent\Skill\Evaluation\EvalCase;
use Symfony\AI\Agent\Skill\Evaluation\EvalSuite;

final class EvalSuiteTest extends TestCase
{
    public function testConstruction()
    {
        $evals = [
            new EvalCase(1, 'prompt1', 'output1'),
            new EvalCase(2, 'prompt2', 'output2'),
        ];

        $suite = new EvalSuite('my-skill', $evals);

        $this->assertSame('my-skill', $suite->getSkillName());
        $this->assertCount(2, $suite->getEvals());
    }

    public function testGetEvalByIdReturnsMatchingCase()
    {
        $evals = [
            new EvalCase(1, 'prompt1', 'output1'),
            new EvalCase(2, 'prompt2', 'output2'),
        ];

        $suite = new EvalSuite('my-skill', $evals);

        $found = $suite->getEvalById(2);
        $this->assertNotNull($found);
        $this->assertSame(2, $found->getId());
        $this->assertSame('prompt2', $found->getPrompt());
    }

    public function testGetEvalByIdReturnsNullWhenNotFound()
    {
        $suite = new EvalSuite('my-skill', [new EvalCase(1, 'prompt', 'output')]);

        $this->assertNull($suite->getEvalById(99));
    }
}
