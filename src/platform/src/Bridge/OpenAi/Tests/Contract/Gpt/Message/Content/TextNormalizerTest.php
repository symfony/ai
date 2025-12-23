<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Tests\Contract\Gpt\Message\Content;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\Contract\Gpt\Message\Content\TextNormalizer;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Model;

class TextNormalizerTest extends TestCase
{
    public function testNormalize()
    {
        $text = new Text('Foo');
        $actual = (new TextNormalizer())->normalize($text, null, [Contract::CONTEXT_MODEL => new Gpt('o3')]);

        $this->assertEquals([
            'type' => 'input_text',
            'text' => $text->getText(),
        ], $actual);
    }

    #[DataProvider('supportsNormalizationProvider')]
    public function testSupportsNormalization(mixed $data, Model $model, bool $expected)
    {
        $this->assertSame(
            $expected,
            (new TextNormalizer())->supportsNormalization($data, null, [Contract::CONTEXT_MODEL => $model])
        );
    }

    public static function supportsNormalizationProvider(): \Generator
    {
        $text = new Text('Foo');
        $gpt = new Gpt('o3');

        yield 'supported' => [$text, $gpt, true];

        yield 'unsupported model' => [$text, new Model('foo'), false];

        yield 'unsupported data' => [[], $gpt, false];
    }
}
