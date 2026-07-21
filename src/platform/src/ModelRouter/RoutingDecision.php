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

use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ProviderInterface;

/**
 * The outcome of a routing decision: the provider serving the request, and
 * optionally the model and options replacing the requested ones.
 *
 * A router that only dispatches to a provider leaves the model and options at
 * null, which keeps the requested ones. A router that selects a model - based
 * on the input, a rule set, or cost - returns it alongside the provider that
 * serves it, and may rewrite the options to fit the selected provider or model.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class RoutingDecision
{
    /**
     * @param non-empty-string|Model|null $model   Replaces the requested model, or null to keep it
     * @param array<string, mixed>|null   $options Replaces the invocation options, or null to keep them
     * @param string                      $reason  Human-readable explanation, surfaced in debugging and profiling
     */
    public function __construct(
        private readonly ProviderInterface $provider,
        private readonly string|Model|null $model = null,
        private readonly ?array $options = null,
        private readonly string $reason = '',
    ) {
    }

    public function getProvider(): ProviderInterface
    {
        return $this->provider;
    }

    /**
     * @return non-empty-string|Model|null
     */
    public function getModel(): string|Model|null
    {
        return $this->model;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getOptions(): ?array
    {
        return $this->options;
    }

    public function getReason(): string
    {
        return $this->reason;
    }
}
