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

use Symfony\AI\Mate\Bridge\Knowledge\Exception\InvalidProviderNameException;
use Symfony\AI\Mate\Bridge\Knowledge\Exception\ProviderNotFoundException;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ProviderRegistry
{
    /**
     * Provider names must be safe to use as a single path component (the cache
     * dir lives at `{cacheDir}/{provider-name}/`) and as a tool argument.
     */
    private const PROVIDER_NAME_PATTERN = '/^[a-z0-9][a-z0-9_-]{0,63}$/';

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
            $name = $provider->getName();

            if (1 !== preg_match(self::PROVIDER_NAME_PATTERN, $name)) {
                throw new InvalidProviderNameException(\sprintf('Knowledge provider "%s" (class "%s") has an invalid name. Allowed characters: lowercase letters, digits, "-" and "_"; must start with a letter or digit; max 64 characters.', $name, $provider::class));
            }

            $this->providers[$name] = $provider;
        }
    }

    public function get(string $name): DocsProviderInterface
    {
        if (!isset($this->providers[$name])) {
            throw new ProviderNotFoundException(\sprintf('Knowledge provider "%s" is not registered. Available providers: "%s".', $name, '' === ($list = implode(', ', array_keys($this->providers))) ? '(none)' : $list));
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
