<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Knowledge\Provider;

use Symfony\AI\Mate\Bridge\Knowledge\Exception\ProviderNotFoundException;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ProviderRegistry
{
    /**
     * @var array<string, DocsProviderInterface>
     */
    private array $providers = [];

    /**
     * @param iterable<DocsProviderInterface> $providers
     */
    public function __construct(iterable $providers = [])
    {
        foreach ($providers as $provider) {
            $this->providers[$provider->getName()] = $provider;
        }
    }

    public function get(string $name): DocsProviderInterface
    {
        if (!isset($this->providers[$name])) {
            throw new ProviderNotFoundException(\sprintf('Knowledge provider "%s" is not registered. Available providers: %s', $name, '' === ($list = implode(', ', array_keys($this->providers))) ? '(none)' : $list));
        }

        return $this->providers[$name];
    }

    public function has(string $name): bool
    {
        return isset($this->providers[$name]);
    }

    /**
     * @return array<string, DocsProviderInterface>
     */
    public function all(): array
    {
        return $this->providers;
    }
}
