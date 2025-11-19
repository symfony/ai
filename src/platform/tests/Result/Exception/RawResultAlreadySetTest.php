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

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Result\Exception\RawResultAlreadySetException;

final class RawResultAlreadySetTest extends TestCase
{
    public function testItHasCorrectExceptionMessage()
    {
        $exception = new RawResultAlreadySetException();

        $this->assertSame('The raw result was already set.', $exception->getMessage());
    }
}
