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

/**
 * Resolves which provider should handle a given model invocation.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface ModelRouterInterface
{
    /**
     * @param non-empty-string            $model     The model name to resolve
     * @param iterable<ProviderInterface> $providers The available providers
     * @param array<mixed>|string|object  $input     The input data
     * @param array<string, mixed>        $options   The invocation options
     */
    public function resolve(string $model, iterable $providers, array|string|object $input, array $options = []): ProviderInterface;
}
