<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\OpenAi\DallE;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\DallE\Base64Image;
use Symfony\AI\Platform\Bridge\OpenAi\DallE\ImageResult;
use Symfony\AI\Platform\Bridge\OpenAi\DallE\UrlImage;

final class ImageResultTest extends TestCase
{
    public function testItCreatesImagesResult()
    {
        $base64Image = new Base64Image('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        $generatedImagesResult = new ImageResult(null, $base64Image);

        $this->assertNull($generatedImagesResult->revisedPrompt);
        $this->assertCount(1, $generatedImagesResult->getContent());
        $this->assertSame($base64Image, $generatedImagesResult->getContent()[0]);
    }

    public function testItCreatesImagesResultWithRevisedPrompt()
    {
        $base64Image = new Base64Image('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        $generatedImagesResult = new ImageResult('revised prompt', $base64Image);

        $this->assertSame('revised prompt', $generatedImagesResult->revisedPrompt);
        $this->assertCount(1, $generatedImagesResult->getContent());
        $this->assertSame($base64Image, $generatedImagesResult->getContent()[0]);
    }

    public function testItIsCreatableWithMultipleImages()
    {
        $image1 = new UrlImage('https://example');
        $image2 = new UrlImage('https://example2');

        $generatedImagesResult = new ImageResult(null, $image1, $image2);

        $this->assertCount(2, $generatedImagesResult->getContent());
        $this->assertSame($image1, $generatedImagesResult->getContent()[0]);
        $this->assertSame($image2, $generatedImagesResult->getContent()[1]);
    }
}
