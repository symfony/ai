<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\Google\Contract;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Google\Contract\AssistantMessageNormalizer;
use Symfony\AI\Platform\Bridge\Google\Gemini;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Model;

#[Small]
#[CoversClass(AssistantMessageNormalizer::class)]
#[UsesClass(Gemini::class)]
#[UsesClass(AssistantMessage::class)]
#[UsesClass(Model::class)]
final class AssistantMessageNormalizerTest extends TestCase
{
    #[Test]
    public function supportsNormalization(): void
    {
        $normalizer = new AssistantMessageNormalizer();

        self::assertTrue($normalizer->supportsNormalization(new AssistantMessage('Hello'), context: [
            Contract::CONTEXT_MODEL => new Gemini(),
        ]));
        self::assertFalse($normalizer->supportsNormalization('not an assistant message'));
    }

    #[Test]
    public function getSupportedTypes(): void
    {
        $normalizer = new AssistantMessageNormalizer();

        self::assertSame([AssistantMessage::class => true], $normalizer->getSupportedTypes(null));
    }

    #[Test]
    public function normalize(): void
    {
        $normalizer = new AssistantMessageNormalizer();
        $message = new AssistantMessage('Great to meet you. What would you like to know?');

        $normalized = $normalizer->normalize($message);

        self::assertSame([['text' => 'Great to meet you. What would you like to know?']], $normalized);
    }
}
