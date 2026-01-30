<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Toolbox\Source;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Toolbox\Source\Source;

final class SourceTest extends TestCase
{
    public function testGetName()
    {
        $source = new Source('test-name', 'test-reference', 'test-content');

        $this->assertSame('test-name', $source->getName());
    }

    public function testGetReference()
    {
        $source = new Source('test-name', 'test-reference', 'test-content');

        $this->assertSame('test-reference', $source->getReference());
    }

    public function testGetContent()
    {
        $source = new Source('test-name', 'test-reference', 'test-content');

        $this->assertSame('test-content', $source->getContent());
    }

    public function testConstructorSetsAllProperties()
    {
        $name = 'document.pdf';
        $reference = 'https://example.com/document.pdf';
        $content = 'This is the document content.';

        $source = new Source($name, $reference, $content);

        $this->assertSame($name, $source->getName());
        $this->assertSame($reference, $source->getReference());
        $this->assertSame($content, $source->getContent());
    }
}
