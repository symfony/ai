<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\OpenAi\Contract\Gpt\Message\Content;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Gemini\Gemini;
use Symfony\AI\Platform\Bridge\OpenAi\Contract\Gpt\Message\Content\DocumentNormalizer;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Message\Content\Document;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Model;

class DocumentNormalizerTest extends TestCase
{
    public function testNormalize()
    {
        $doc = Document::fromFile(\dirname(__DIR__, 9).'/fixtures/document.pdf');
        $actual = (new DocumentNormalizer())->normalize($doc, null, [Contract::CONTEXT_MODEL => new Gpt('o3')]);

        $this->assertEquals([
            'type' => 'input_file',
            'filename' => $doc->getFilename(),
            'file_data' => $doc->asDataUrl(),
        ], $actual);
    }

    #[DataProvider('supportsNormalizationProvider')]
    public function testSupportsNormalization(mixed $data, Model $model, bool $expected)
    {
        $this->assertSame(
            $expected,
            (new DocumentNormalizer())->supportsNormalization($data, null, [Contract::CONTEXT_MODEL => $model])
        );
    }

    public static function supportsNormalizationProvider(): \Generator
    {
        $doc = Document::fromFile(\dirname(__DIR__, 9).'/fixtures/document.pdf');
        $gpt = new Gpt('o3', [Capability::INPUT_PDF]);

        yield 'supported' => [$doc, $gpt, true];

        yield 'unsupported model' => [$doc, new Gemini('foo', [Capability::INPUT_PDF]), false];

        yield 'model lacks image input capability' => [$doc, new Gpt('o3'), false];

        yield 'unsupported data' => [new Text('foo'), $gpt, false];
    }
}
