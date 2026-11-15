<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Message\Content;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Message\Content\Collection;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Content\Text;

final class CollectionTest extends TestCase
{
    public function testItWrapsStringsIntoText()
    {
        $image = new Image('image', 'image/png');
        $stringable = new class implements \Stringable {
            public function __toString(): string
            {
                return 'A caption.';
            }
        };

        $content = (new Collection('A photo to classify.', $image, $stringable))->getContent();

        $this->assertCount(3, $content);
        $this->assertInstanceOf(Text::class, $content[0]);
        $this->assertSame('A photo to classify.', $content[0]->getText());
        $this->assertSame($image, $content[1]);
        $this->assertInstanceOf(Text::class, $content[2]);
        $this->assertSame('A caption.', $content[2]->getText());
    }
}
