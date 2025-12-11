<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\Decart\Contract;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Decart\Contract\DecartContract;
use Symfony\AI\Platform\Bridge\Decart\Decart;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Content\Video;

final class DecartContractTest extends TestCase
{
    public function testItCanCreatePayloadWithImage()
    {
        $image = Image::fromFile(\dirname(__DIR__, 6).'/fixtures/image.jpg');

        $contract = DecartContract::create();

        $payload = $contract->createRequestPayload(new Decart('lucy-pro-i2i'), $image);

        $this->assertSame([
            'type' => 'input_image',
            'input_image' => [
                'data' => $image->asBase64(),
                'path' => $image->asPath(),
                'format' => 'jpg',
            ],
        ], $payload);
    }

    public function testItCanCreatePayloadWithVideo()
    {
        $image = Video::fromFile(\dirname(__DIR__, 6).'/fixtures/ocean.mp4');

        $contract = DecartContract::create();

        $payload = $contract->createRequestPayload(new Decart('lucy-pro-v2v'), $image);

        $this->assertSame([
            'type' => 'input_video',
            'input_video' => [
                'data' => $image->asBase64(),
                'path' => $image->asPath(),
                'format' => 'video/mp4',
            ],
        ], $payload);
    }
}
