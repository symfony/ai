<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\ModelRouter;

use Symfony\AI\Platform\Exception\ModelNotFoundException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelRouterInterface;

/**
 * Routes to the first provider supporting the requested model.
 *
 * This is the default, zero-configuration router. It iterates through all
 * registered providers and returns the first one that supports the model, either
 * via its model catalog (model name) or its model clients (fully defined model).
 *
 * It never replaces the requested model, which makes it the terminal resolver
 * that model-selecting routers delegate to.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class CatalogBasedModelRouter implements ModelRouterInterface
{
    public function resolve(string|Model $model, iterable $providers, array|string|object $input, array $options = []): RoutingDecision
    {
        foreach ($providers as $provider) {
            if ($provider->supports($model)) {
                return new RoutingDecision($provider);
            }
        }

        throw new ModelNotFoundException(\sprintf('No provider found for model "%s".', $model instanceof Model ? $model->getName() : $model));
    }
}
