<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Higgsfield\Tests\Contract;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Higgsfield\Contract\HiggsfieldContract;
use Symfony\AI\Platform\Bridge\Higgsfield\Higgsfield;
use Symfony\AI\Platform\Message\Content\Image;

final class HiggsfieldContractTest extends TestCase
{
    public function testItCanCreatePayloadWithImage()
    {
        $image = Image::fromFile(\dirname(__DIR__, 7).'/fixtures/image.jpg');

        $contract = HiggsfieldContract::create();

        $payload = $contract->createRequestPayload(new Higgsfield('v1/image2video/dop'), $image);

        $this->assertSame([
            'type' => 'image_url',
            'image_url' => $image->asDataUrl(),
        ], $payload);
    }
}
