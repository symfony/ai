<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Contract\Normalizer\Message\Content;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Contract\Normalizer\Message\Content\TemplateNormalizer;
use Symfony\AI\Platform\Message\Template;

final class TemplateNormalizerTest extends TestCase
{
    private TemplateNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new TemplateNormalizer();
    }

    public function testSupportsNormalization()
    {
        $this->assertTrue($this->normalizer->supportsNormalization(Template::string('You are a {role}.')));
        $this->assertFalse($this->normalizer->supportsNormalization(new \stdClass()));
    }

    public function testGetSupportedTypes()
    {
        $this->assertSame([Template::class => true], $this->normalizer->getSupportedTypes(null));
    }

    public function testNormalize()
    {
        $template = new Template('You are a {role}.', 'string');

        $expected = [
            'type' => 'template',
            'template' => 'You are a {role}.',
            'template_type' => 'string',
        ];

        $this->assertSame($expected, $this->normalizer->normalize($template));
    }
}
