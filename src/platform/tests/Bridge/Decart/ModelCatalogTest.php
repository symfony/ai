<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\Decart;

use Symfony\AI\Platform\Bridge\Decart\Decart;
use Symfony\AI\Platform\Bridge\Decart\ModelCatalog;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\Test\ModelCatalogTestCase;

final class ModelCatalogTest extends ModelCatalogTestCase
{
    public static function modelsProvider(): iterable
    {
        yield 'lucy-dev-i2v' => ['lucy-dev-i2v', Decart::class, [Capability::IMAGE_TO_VIDEO, Capability::VIDEO_TO_VIDEO]];
        yield 'lucy-pro-t2i' => ['lucy-pro-t2i', Decart::class, [Capability::TEXT_TO_IMAGE]];
        yield 'lucy-pro-t2v' => ['lucy-pro-t2v', Decart::class, [Capability::TEXT_TO_VIDEO, Capability::IMAGE_TO_VIDEO]];
        yield 'lucy-pro-i2i' => ['lucy-pro-i2i', Decart::class, [Capability::IMAGE_TO_IMAGE]];
        yield 'lucy-pro-i2v' => ['lucy-pro-i2v', Decart::class, [Capability::IMAGE_TO_VIDEO]];
        yield 'lucy-pro-v2v' => ['lucy-pro-v2v', Decart::class, [Capability::VIDEO_TO_VIDEO]];
        yield 'lucy-pro-flf2v' => ['lucy-pro-flf2v', Decart::class, [Capability::IMAGE_TO_VIDEO]];
    }

    protected function createModelCatalog(): ModelCatalogInterface
    {
        return new ModelCatalog();
    }
}
