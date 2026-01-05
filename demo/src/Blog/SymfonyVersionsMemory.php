<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Blog;

use Symfony\AI\Agent\Input;
use Symfony\AI\Agent\Memory\Memory;
use Symfony\AI\Agent\Memory\MemoryProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class SymfonyVersionsMemory implements MemoryProviderInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
    ) {
    }

    public function load(Input $input): array
    {
        return $this->cache->get('symfony_version_memory', function (ItemInterface $item) {
            $item->expiresAfter(3600);

            $response = $this->httpClient->request('GET', 'https://symfony.com/versions.json');
            $data = $response->toArray();

            return [
                new Memory(<<<MEMORY
                    # Current Symfony Versions:
                    LTS: {$data['lts']}
                    Latest: {$data['latest']}
                    In development: {$data['dev']}
                    MEMORY),
            ];
        });
    }
}
