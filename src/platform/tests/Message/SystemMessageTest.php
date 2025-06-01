<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Message;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Message\Role;
use Symfony\AI\Platform\Message\SystemMessage;

#[CoversClass(SystemMessage::class)]
#[Small]
final class SystemMessageTest extends TestCase
{
    #[Test]
    public function constructionIsPossible(): void
    {
        $message = new SystemMessage('foo');

        self::assertSame(Role::System, $message->getRole());
        self::assertSame('foo', $message->content);
    }
}
