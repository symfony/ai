<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Tests\Command\BatchFixtures;

use Symfony\AI\Mate\Attribute\MateResourceTemplate;
use Symfony\AI\Mate\Attribute\MateTool;
use Symfony\AI\Mate\Encoding\ResponseEncoder;

/**
 * List-shaped results for the chaining tests of `tools:call-batch`.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ChainTool
{
    /**
     * @param int $count Number of profiles returned
     */
    #[MateTool(name: 'chain-list', title: 'Chain List', description: 'List profiles inside the untrusted envelope')]
    public function list(int $count = 2): string
    {
        $profiles = [];
        for ($i = 1; $i <= $count; ++$i) {
            $profiles[] = ['token' => 'tok'.$i, 'status' => 200 + $i, 'id' => $i];
        }

        return ResponseEncoder::encodeUntrusted(['profiles' => $profiles, 'total' => $count]);
    }

    /**
     * @param string $token Profile token
     */
    #[MateTool(name: 'chain-get', title: 'Chain Get', description: 'Get one profile')]
    public function get(string $token): string
    {
        return ResponseEncoder::encodeUntrusted(['token' => $token, 'method' => 'GET', 'logs' => ['a', 'b', 'c']]);
    }

    /**
     * @param int    $limit Maximum number of hits
     * @param string $query Search text
     */
    #[MateTool(name: 'chain-search', title: 'Chain Search', description: 'Search with a typed limit')]
    public function search(int $limit, string $query = ''): string
    {
        return ResponseEncoder::encode(['limit' => $limit, 'limit_type' => get_debug_type($limit), 'query' => $query]);
    }

    /**
     * @param array<mixed> $items Items echoed back
     */
    #[MateTool(name: 'chain-items', title: 'Chain Items', description: 'Echo an array parameter')]
    public function items(array $items): string
    {
        return ResponseEncoder::encode(['items' => $items]);
    }

    #[MateResourceTemplate(
        uriTemplate: 'chain://profile/{token}',
        name: 'chain-profile',
        description: 'A profile as an enveloped resource',
        mimeType: 'application/json',
    )]
    public function profile(string $token): string
    {
        return ResponseEncoder::encodeUntrusted(['token' => $token, 'db' => ['queries' => 7, 'time' => 1.5]]);
    }
}
