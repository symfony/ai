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
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Result\Choice;
use Symfony\AI\Platform\Result\ChoiceResult;

#[CoversClass(ChoiceResult::class)]
#[UsesClass(Choice::class)]
#[Small]
final class ChoiceResultTest extends TestCase
{
    #[Test]
    public function choiceResultCreation(): void
    {
        $choice1 = new Choice('choice1');
        $choice2 = new Choice(null);
        $choice3 = new Choice('choice3');
        $result = new ChoiceResult($choice1, $choice2, $choice3);

        $this->assertCount(3, $result->getContent());
        $this->assertSame('choice1', $result->getContent()[0]->getContent());
        $this->assertNull($result->getContent()[1]->getContent());
        $this->assertSame('choice3', $result->getContent()[2]->getContent());
    }

    #[Test]
    public function choiceResultWithNoChoices(): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('Result must have at least one choice.');

        new ChoiceResult();
    }
}
