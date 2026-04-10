<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\ModelResolver;

use Symfony\AI\Platform\Exception\ModelNotFoundException;
use Symfony\AI\Platform\ModelResolverInterface;
use Symfony\AI\Platform\ProviderInterface;

/**
 * Routes to the first provider whose model catalog contains the requested model.
 *
 * This is the default, zero-configuration resolver. It iterates through all
 * registered providers and returns the first one that supports the model name.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class CatalogBasedModelResolver implements ModelResolverInterface
{
    public function resolve(string $model, iterable $providers, array|string|object $input, array $options = []): ProviderInterface
    {
        foreach ($providers as $provider) {
            if ($provider->supports($model)) {
                return $provider;
            }
        }

        throw new ModelNotFoundException(\sprintf('No provider found for model "%s".', $model));
    }
}
