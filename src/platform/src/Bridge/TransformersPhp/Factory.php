<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\TransformersPhp;

use Codewithkyrian\Transformers\Transformers;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\Platform;
use Symfony\AI\Platform\Provider;
use Symfony\AI\Platform\ProviderInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Factory
{
    /**
     * @param non-empty-string $name
     */
    public static function createProvider(
        ModelCatalogInterface $modelCatalog = new ModelCatalog(),
        ?EventDispatcherInterface $eventDispatcher = null,
        string $name = 'transformersphp',
    ): ProviderInterface {
        if (!class_exists(Transformers::class)) {
            throw new RuntimeException('For using the TransformersPHP with FFI to run models in PHP, the codewithkyrian/transformers package is required. Try running "composer require codewithkyrian/transformers".');
        }

        return new Provider($name, [new ModelClient()], [new ResultConverter()], $modelCatalog, eventDispatcher: $eventDispatcher);
    }

    /**
     * @param non-empty-string $name
     */
    public static function createPlatform(
        ModelCatalogInterface $modelCatalog = new ModelCatalog(),
        ?EventDispatcherInterface $eventDispatcher = null,
        string $name = 'transformersphp',
    ): Platform {
        return new Platform([
            self::createProvider($modelCatalog, $eventDispatcher, $name),
        ]);
    }
}
