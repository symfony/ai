<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Result;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Result\StreamResult;

final class StreamResultTest extends TestCase
{
    public function testGetContent()
    {
        $generator = (function () {
            yield 'Hello';
            yield ' ';
            yield 'World';
        })();

        $result = new StreamResult($generator);
        $content = iterator_to_array($result->getContent());

        $this->assertSame(['Hello', ' ', 'World'], $content);
    }

    public function testGetContentWithMultipleChunks()
    {
        $generator = (function () {
            yield 'Chunk';
            yield '1';
            yield 'Chunk';
            yield '2';
        })();

        $result = new StreamResult($generator);
        $content = iterator_to_array($result->getContent());

        $this->assertSame(['Chunk', '1', 'Chunk', '2'], $content);
    }

    public function testGetContentWithEmptyGenerator()
    {
        $generator = (function () {
            // Empty generator
            if (false) {
                yield;
            }
        })();

        $result = new StreamResult($generator);
        $content = iterator_to_array($result->getContent());

        $this->assertSame([], $content);
    }
}
