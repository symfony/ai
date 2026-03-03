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

final class EvalCaseTest extends TestCase
{
    public function testConstructionWithRequiredFields()
    {
        $evalCase = new EvalCase(1, 'What is PHP?', 'PHP is a programming language.');

        $this->assertSame(1, $evalCase->getId());
        $this->assertSame('What is PHP?', $evalCase->getPrompt());
        $this->assertSame('PHP is a programming language.', $evalCase->getExpectedOutput());
        $this->assertSame([], $evalCase->getFiles());
        $this->assertSame([], $evalCase->getAssertions());
    }

    public function testConstructionWithAllFields()
    {
        $files = ['src/main.php', 'config.yaml'];
        $assertions = ['Output mentions PHP', 'Output is concise'];

        $evalCase = new EvalCase(2, 'Review this code', 'Code looks good', $files, $assertions);

        $this->assertSame(2, $evalCase->getId());
        $this->assertSame('Review this code', $evalCase->getPrompt());
        $this->assertSame('Code looks good', $evalCase->getExpectedOutput());
        $this->assertSame($files, $evalCase->getFiles());
        $this->assertSame($assertions, $evalCase->getAssertions());
    }
}
