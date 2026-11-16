<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Ollama\Tests\Contract;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Ollama\Contract\OllamaContract;
use Symfony\AI\Platform\Bridge\Ollama\Contract\UserMessageNormalizer;
use Symfony\AI\Platform\Bridge\Ollama\Ollama;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Content\ImageUrl;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Model;

final class UserMessageNormalizerTest extends TestCase
{
    private UserMessageNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new UserMessageNormalizer();
    }

    public function testSupportsNormalization()
    {
        $message = new UserMessage(new Text('Hello'));

        $this->assertTrue($this->normalizer->supportsNormalization($message, context: [
            Contract::CONTEXT_MODEL => new Ollama('qwen3-vl'),
        ]));
        $this->assertFalse($this->normalizer->supportsNormalization($message, context: [
            Contract::CONTEXT_MODEL => new Model('any-model'),
        ]));
        $this->assertFalse($this->normalizer->supportsNormalization('not a user message'));
    }

    public function testGetSupportedTypes()
    {
        $this->assertSame([UserMessage::class => true], $this->normalizer->getSupportedTypes(null));
    }

    /**
     * @param array{role: 'user', content: string, images?: list<string>} $expected
     */
    #[DataProvider('normalizeDataProvider')]
    public function testNormalize(UserMessage $message, array $expected)
    {
        $this->assertSame($expected, $this->normalizer->normalize($message));
    }

    /**
     * @return iterable<string, array{UserMessage, array{role: 'user', content: string, images?: list<string>}}>
     */
    public static function normalizeDataProvider(): iterable
    {
        yield 'text only' => [
            new UserMessage(new Text('Describe this photo.')),
            ['role' => 'user', 'content' => 'Describe this photo.'],
        ];

        yield 'text and image' => [
            new UserMessage(new Text('Describe this photo.'), new Image('image-bytes', 'image/jpeg')),
            ['role' => 'user', 'content' => 'Describe this photo.', 'images' => [base64_encode('image-bytes')]],
        ];

        yield 'image only' => [
            new UserMessage(new Image('image-bytes', 'image/png')),
            ['role' => 'user', 'content' => '', 'images' => [base64_encode('image-bytes')]],
        ];

        yield 'multiple texts and images' => [
            new UserMessage(
                new Text('Compare these photos.'),
                new Image('first', 'image/jpeg'),
                new Text('Which one is brighter?'),
                new Image('second', 'image/png'),
            ),
            [
                'role' => 'user',
                'content' => "Compare these photos.\nWhich one is brighter?",
                'images' => [base64_encode('first'), base64_encode('second')],
            ],
        ];
    }

    public function testNormalizeThrowsOnUnsupportedContent()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('Content of type "%s" is not supported by Ollama user messages, only "%s" and "%s" are.', ImageUrl::class, Text::class, Image::class));

        $this->normalizer->normalize(new UserMessage(new ImageUrl('https://example.com/photo.jpg')));
    }

    public function testContractSendsImagesInOllamaFormat()
    {
        $messages = new MessageBag(
            Message::ofUser('Describe this photo.', new Image('image-bytes', 'image/jpeg')),
        );

        $payload = OllamaContract::create()->createRequestPayload(new Ollama('qwen3-vl'), $messages);

        $this->assertSame([
            'messages' => [
                ['role' => 'user', 'content' => 'Describe this photo.', 'images' => [base64_encode('image-bytes')]],
            ],
            'model' => 'qwen3-vl',
        ], $payload);
    }
}
