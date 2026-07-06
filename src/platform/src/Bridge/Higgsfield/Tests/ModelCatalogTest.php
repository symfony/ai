<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Higgsfield\Tests;

use Symfony\AI\Platform\Bridge\Higgsfield\Higgsfield;
use Symfony\AI\Platform\Bridge\Higgsfield\ModelCatalog;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\Test\ModelCatalogTestCase;

final class ModelCatalogTest extends ModelCatalogTestCase
{
    public static function modelsProvider(): iterable
    {
        yield 'flux-pro/kontext/max/text-to-image' => ['flux-pro/kontext/max/text-to-image', Higgsfield::class, [Capability::TEXT_TO_IMAGE]];
        yield 'bytedance/seedream/v4/text-to-image' => ['bytedance/seedream/v4/text-to-image', Higgsfield::class, [Capability::TEXT_TO_IMAGE]];
        yield 'v1/text2image/soul' => ['v1/text2image/soul', Higgsfield::class, [Capability::TEXT_TO_IMAGE]];
        yield 'v1/image2video/dop' => ['v1/image2video/dop', Higgsfield::class, [Capability::IMAGE_TO_VIDEO]];
    }

    protected function createModelCatalog(): ModelCatalogInterface
    {
        return new ModelCatalog();
    }
}
