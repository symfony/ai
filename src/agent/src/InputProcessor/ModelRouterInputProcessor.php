<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\InputProcessor;

use Symfony\AI\Agent\Input;
use Symfony\AI\Agent\InputProcessorInterface;
use Symfony\AI\Agent\Router\RouterContext;
use Symfony\AI\Agent\Router\RouterInterface;

/**
 * Input processor that routes requests to appropriate models based on input characteristics.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ModelRouterInputProcessor implements InputProcessorInterface
{
    public function __construct(
        private readonly RouterInterface $router,
    ) {
    }

    public function processInput(Input $input): void
    {
        // Get platform from input
        $platform = $input->getPlatform();

        if (null === $platform) {
            return; // No platform available, skip routing
        }

        // Build context with platform and default model from input
        $context = new RouterContext(
            platform: $platform,
            catalog: $platform->getModelCatalog(),
            metadata: [
                'default_model' => $input->getModel(), // Agent's default model
            ],
        );

        // Route
        $result = $this->router->route($input, $context);

        if (null === $result) {
            return; // Router couldn't handle, keep existing model
        }

        // Apply transformation if specified
        if ($transformer = $result->getTransformer()) {
            $transformedInput = $transformer->transform($input, $context);
            $input->setMessageBag($transformedInput->getMessageBag());
            $input->setOptions($transformedInput->getOptions());
        }

        // Apply routing decision
        $input->setModel($result->getModelName());
    }
}
