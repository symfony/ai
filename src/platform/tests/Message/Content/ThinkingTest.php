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
use Symfony\AI\Platform\Message\Content\Thinking;

final class ThinkingTest extends TestCase
{
    public function testConstructionIsPossible()
    {
        $obj = new Thinking('First, I need to check the file system structure...');

        $this->assertSame('First, I need to check the file system structure...', $obj->getThinking());
    }
}
