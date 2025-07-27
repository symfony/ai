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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Result\ChoiceResult;
use Symfony\AI\Platform\Result\TextResult;

#[CoversClass(ChoiceResult::class)]
#[Small]
final class ChoiceResultTest extends TestCase
{
    public function testChoiceResultCreation()
    {
        $choice1 = new TextResult('choice1');
        $choice3 = new TextResult('choice2');
        $result = new ChoiceResult($choice1, $choice3);

        $this->assertCount(2, $result->getContent());
        $this->assertSame('choice1', $result->getContent()[0]->getContent());
        $this->assertSame('choice2', $result->getContent()[1]->getContent());
    }

    public function testChoiceResultWithNoChoices()
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('A choice result must contain at least two results.');

        new ChoiceResult();
    }
}
