<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Symfony\Tests\Fixtures;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

/**
 * Test collector for profiler fixtures.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class TestCollector extends DataCollector
{
    private string $collectorName = 'test';

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        string $name,
        array $data = [],
    ) {
        $this->collectorName = $name;
        $this->data = $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        return [
            'collectorName' => $this->collectorName,
            'data' => $this->data,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function __unserialize(array $data): void
    {
        $this->collectorName = $data['collectorName'];
        $this->data = $data['data'];
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        // No collection needed for test fixtures
    }

    public function getName(): string
    {
        return $this->collectorName;
    }

    public function reset(): void
    {
        $this->data = [];
    }
}
