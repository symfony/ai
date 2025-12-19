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
use Symfony\AI\Platform\ModelCatalog\FallbackModelCatalog;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Platform;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\Component\HttpClient\Response\MockResponse;

final class PlatformTestHandler implements ModelClientInterface, ResultConverterInterface
{
    public int $createCalls = 0;

    public function __construct(
        private readonly ?ResultInterface $create = null,
    ) {
    }

    public static function createPlatform(?ResultInterface $create = null, ModelCatalogInterface $modelCatalog = new FallbackModelCatalog()): Platform
    {
        $handler = new self($create);

        return new Platform([$handler], [$handler], $modelCatalog);
    }

    public function supports(Model $model): bool
    {
        return true;
    }

    public function request(Model $model, array|string|object $payload, array $options = []): RawHttpResult
    {
        ++$this->createCalls;

        return new RawHttpResult(new MockResponse());
    }

    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        return $this->create ?? new VectorResult(new Vector([1, 2, 3]));
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }
}
