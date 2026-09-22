<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\EdenAi\Tests\Contract;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\EdenAi\Contract\DocumentUrlNormalizer;
use Symfony\AI\Platform\Bridge\EdenAi\DocumentParser;
use Symfony\AI\Platform\Bridge\EdenAi\Ocr;
use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Message\Content\DocumentUrl;

/**
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class DocumentUrlNormalizerTest extends TestCase
{
    public function testSupportsNormalization()
    {
        $normalizer = new DocumentUrlNormalizer();
        $documentUrl = new DocumentUrl('https://example.com/document.pdf');

        $this->assertTrue($normalizer->supportsNormalization($documentUrl, context: [
            Contract::CONTEXT_MODEL => new Ocr('ocr/ocr/google'),
        ]));
        $this->assertTrue($normalizer->supportsNormalization($documentUrl, context: [
            Contract::CONTEXT_MODEL => new DocumentParser('ocr/resume_parser/affinda'),
        ]));
        $this->assertFalse($normalizer->supportsNormalization($documentUrl, context: [
            Contract::CONTEXT_MODEL => new CompletionsModel('openai/gpt-4o'),
        ]));
        $this->assertFalse($normalizer->supportsNormalization('not a document url'));
    }

    public function testGetSupportedTypes()
    {
        $normalizer = new DocumentUrlNormalizer();

        $this->assertSame([DocumentUrl::class => true], $normalizer->getSupportedTypes(null));
    }

    public function testNormalize()
    {
        $normalizer = new DocumentUrlNormalizer();

        $this->assertSame(
            ['file' => 'https://example.com/document.pdf'],
            $normalizer->normalize(new DocumentUrl('https://example.com/document.pdf')),
        );
    }
}
