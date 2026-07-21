<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform;

use Symfony\AI\Platform\Exception\ModelNotFoundException;
use Symfony\AI\Platform\ModelRouter\RoutingDecision;

/**
 * Resolves which provider handles a given model invocation, and optionally which model.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface ModelRouterInterface
{
    /**
     * @param non-empty-string|Model      $model     The requested model name to resolve via the catalog, or a fully defined model
     * @param iterable<ProviderInterface> $providers The available providers
     * @param array<mixed>|string|object  $input     The input data
     * @param array<string, mixed>        $options   The invocation options
     *
     * @throws ModelNotFoundException When no provider can serve the model
     */
    public function resolve(string|Model $model, iterable $providers, array|string|object $input, array $options = []): RoutingDecision;
}
