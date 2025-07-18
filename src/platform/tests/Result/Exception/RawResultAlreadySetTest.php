<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Result\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Result\Exception\RawResultAlreadySetException;

#[CoversClass(RawResultAlreadySetException::class)]
#[Small]
final class RawResultAlreadySetTest extends TestCase
{
    #[Test]
    public function itHasCorrectExceptionMessage(): void
    {
        $exception = new RawResultAlreadySetException();

        self::assertSame('The raw result was already set.', $exception->getMessage());
    }
}
