<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\Image;

final class ImageTest extends TestCase
{
    public function testItCreatesImageWithDefaultSettings()
    {
        $image = new Image('gpt-image-1');

        $this->assertSame('gpt-image-1', $image->getName());
        $this->assertSame([], $image->getOptions());
    }

    public function testItCreatesImageWithCustomSettings()
    {
        $image = new Image('gpt-image-1', [], ['n' => 2]);

        $this->assertSame('gpt-image-1', $image->getName());
        $this->assertSame(['n' => 2], $image->getOptions());
    }
}
