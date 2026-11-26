<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Anthropic\Tests\Contract;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Anthropic\Contract\DocumentNormalizer;
use Symfony\AI\Platform\Message\Content\Document;

final class DocumentNormalizerTest extends TestCase
{
    public function testNormalizesAPdfAsBase64Source()
    {
        $normalized = (new DocumentNormalizer())->normalize(new Document('%PDF-1.7', 'application/pdf'));

        $this->assertSame([
            'type' => 'document',
            'source' => ['type' => 'base64', 'media_type' => 'application/pdf', 'data' => base64_encode('%PDF-1.7')],
        ], $normalized);
    }

    #[TestWith(['text/plain'])]
    #[TestWith(['text/markdown'])]
    #[TestWith(['text/csv'])]
    public function testNormalizesATextDocumentAsPlainTextSource(string $format)
    {
        $normalized = (new DocumentNormalizer())->normalize(new Document("Refunds are accepted within 30 days.\n", $format));

        $this->assertSame([
            'type' => 'document',
            'source' => ['type' => 'text', 'media_type' => 'text/plain', 'data' => "Refunds are accepted within 30 days.\n"],
        ], $normalized);
    }
}
