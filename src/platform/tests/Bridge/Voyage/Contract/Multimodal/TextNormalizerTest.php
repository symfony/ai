<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\Voyage\Contract\Multimodal;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Bridge\Voyage\Contract\Multimodal\CollectionNormalizer;
use Symfony\AI\Platform\Bridge\Voyage\Contract\Multimodal\TextNormalizer;
use Symfony\AI\Platform\Bridge\Voyage\Voyage;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Model;

final class TextNormalizerTest extends TestCase
{
    public function testNormalize()
    {
        $text = 'Symfony rules';

        $normalizer = new TextNormalizer();
        $normalized = $normalizer->normalize(new Text($text));

        $this->assertEquals([[
            CollectionNormalizer::KEY_CONTENT => [
                [
                    'type' => 'text',
                    'text' => $text,
                ]],
        ]], $normalized);
    }

    #[DataProvider('supportsNormalizationDataProvider')]
    public function testSupportsNormalization(mixed $data, Model $model, bool $result)
    {
        $normalizer = new TextNormalizer();
        $this->assertEquals($result, $normalizer->supportsNormalization($data, context: [
            Contract::CONTEXT_MODEL => $model,
        ]));
    }

    public static function supportsNormalizationDataProvider(): \Generator
    {
        $text = new Text('Symfony rules');

        yield 'supported' => [$text, new Voyage('voyage-multimodal-3', [Capability::INPUT_MULTIMODAL]), true];
        yield 'not text' => [[], new Voyage('voyage-multimodal-3', [Capability::INPUT_MULTIMODAL]), false];
        yield 'non-multimodal model' => [$text, new Voyage('voyage-3.5'), false];
        yield 'unsupported model' => [$text, new Gpt('gpt-40'), false];
    }
}
