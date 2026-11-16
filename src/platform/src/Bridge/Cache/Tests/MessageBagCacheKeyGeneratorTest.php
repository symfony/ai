<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Cache\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Cache\MessageBagCacheKeyGenerator;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

final class MessageBagCacheKeyGeneratorTest extends TestCase
{
    public function testTheKeyIgnoresIdsAndMetadataWithoutNormalizingIt()
    {
        $answer = Message::ofAssistant('Hi');
        $answer->getMetadata()->add('answered_at', new \DateTimeImmutable());
        $generator = new MessageBagCacheKeyGenerator();

        $key = $generator->generate(new MessageBag(Message::ofUser('Hello'), $answer));

        $this->assertSame($generator->generate(new MessageBag(Message::ofUser('Hello'), Message::ofAssistant('Hi'))), $key);
    }
}
