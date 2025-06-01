<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Response\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Response\Exception\RawResponseAlreadySetException;

#[CoversClass(RawResponseAlreadySetException::class)]
#[Small]
final class RawResponseAlreadySetTest extends TestCase
{
    #[Test]
    public function itHasCorrectExceptionMessage(): void
    {
        $exception = new RawResponseAlreadySetException();

        self::assertSame('The raw response was already set.', $exception->getMessage());
    }
}
