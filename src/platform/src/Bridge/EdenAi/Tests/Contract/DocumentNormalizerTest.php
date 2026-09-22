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
use Symfony\AI\Platform\Bridge\EdenAi\Contract\DocumentNormalizer;
use Symfony\AI\Platform\Bridge\EdenAi\DocumentParser;
use Symfony\AI\Platform\Bridge\EdenAi\Ocr;
use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Message\Content\Document;

/**
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class DocumentNormalizerTest extends TestCase
{
    public function testSupportsNormalization()
    {
        $normalizer = new DocumentNormalizer();
        $document = new Document('binary-pdf-content', 'application/pdf');

        $this->assertTrue($normalizer->supportsNormalization($document, context: [
            Contract::CONTEXT_MODEL => new Ocr('ocr/ocr/google'),
        ]));
        $this->assertTrue($normalizer->supportsNormalization($document, context: [
            Contract::CONTEXT_MODEL => new DocumentParser('ocr/resume_parser/affinda'),
        ]));
        $this->assertFalse($normalizer->supportsNormalization($document, context: [
            Contract::CONTEXT_MODEL => new CompletionsModel('openai/gpt-4o'),
        ]));
    }

    public function testNormalize()
    {
        $normalizer = new DocumentNormalizer();
        $document = new Document('binary-pdf-content', 'application/pdf');

        $normalized = $normalizer->normalize($document);

        $this->assertSame(['file_data'], array_keys($normalized));
        $this->assertNull($normalized['file_data']['filename']);
        $this->assertSame('application/pdf', $normalized['file_data']['format']);
        $this->assertSame($document->asBase64(), $normalized['file_data']['data']);
    }
}
