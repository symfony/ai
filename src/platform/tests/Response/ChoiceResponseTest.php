<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Response;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Response\Choice;
use Symfony\AI\Platform\Response\ChoiceResponse;

#[CoversClass(ChoiceResponse::class)]
#[UsesClass(Choice::class)]
#[Small]
final class ChoiceResponseTest extends TestCase
{
    #[Test]
    public function choiceResponseCreation(): void
    {
        $choice1 = new Choice('choice1');
        $choice2 = new Choice(null);
        $choice3 = new Choice('choice3');
        $response = new ChoiceResponse($choice1, $choice2, $choice3);

        self::assertCount(3, $response->getContent());
        self::assertSame('choice1', $response->getContent()[0]->getContent());
        self::assertNull($response->getContent()[1]->getContent());
        self::assertSame('choice3', $response->getContent()[2]->getContent());
    }

    #[Test]
    public function choiceResponseWithNoChoices(): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('Response must have at least one choice.');

        new ChoiceResponse();
    }
}
