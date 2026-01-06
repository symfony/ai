<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Result;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Result\ChoiceResult;
use Symfony\AI\Platform\Result\TextResult;

final class ChoiceResultTest extends TestCase
{
    public function testChoiceResultCreation()
    {
        $result = new ChoiceResult([new TextResult('choice1'), new TextResult('choice2')]);

        $this->assertCount(2, $result->getContent());
        $this->assertSame('choice1', $result->getContent()[0]->getContent());
        $this->assertSame('choice2', $result->getContent()[1]->getContent());
    }

    public function testChoiceResultWithNoChoices()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A choice result must contain at least two results.');

        new ChoiceResult([]);
    }

    public function testChoiceResultWithOneChoice()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A choice result must contain at least two results.');

        new ChoiceResult([new TextResult('choice1')]);
    }
}
