<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Tests\Double;

use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Platform;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\Component\HttpClient\Response\MockResponse;

final class PlatformTestHandler implements ModelClientInterface, ResultConverterInterface
{
    public int $createCalls = 0;
    private int $convertCallIndex = 0;
    /** @var Vector[] */
    private array $vectors = [];

    private bool $isBatchMode = false;

    public function __construct(
        private readonly ?ResultInterface $create = null,
    ) {
        // If a VectorResult with multiple vectors is provided, extract them for sequential access
        if ($create instanceof VectorResult) {
            // Get vectors from the VectorResult
            foreach ($create->getContent() as $vector) {
                $this->vectors[] = $vector;
            }
        }
    }

    public static function createPlatform(?ResultInterface $create = null): Platform
    {
        $handler = new self($create);

        return new Platform([$handler], [$handler]);
    }

    public function supports(Model $model): bool
    {
        return true;
    }

    public function request(Model $model, array|string|object $payload, array $options = []): RawHttpResult
    {
        ++$this->createCalls;

        // Detect batch mode when payload is an array (multiple documents)
        $this->isBatchMode = \is_array($payload);

        return new RawHttpResult(new MockResponse());
    }

    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        // In batch mode, return all vectors at once
        if ($this->isBatchMode && $this->create instanceof VectorResult) {
            return $this->create;
        }

        // In sequential mode, return vectors one at a time
        if ([] !== $this->vectors) {
            // Return vectors one at a time
            if ($this->convertCallIndex < \count($this->vectors)) {
                $vector = $this->vectors[$this->convertCallIndex];
                ++$this->convertCallIndex;

                return new VectorResult($vector);
            }

            // Fall back to original create if we've exhausted vectors
            return $this->create ?? new VectorResult(new Vector([1, 2, 3]));
        }

        return $this->create ?? new VectorResult(new Vector([1, 2, 3]));
    }
}
