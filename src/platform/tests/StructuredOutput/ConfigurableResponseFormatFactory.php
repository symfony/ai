<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\StructuredOutput;

use Symfony\AI\Platform\StructuredOutput\ResponseFormatFactoryInterface;

final class ConfigurableResponseFormatFactory implements ResponseFormatFactoryInterface
{
    private ?object $lastInstance = null;

    /**
     * @param array<mixed> $responseFormat
     */
    public function __construct(
        private readonly array $responseFormat = [],
    ) {
    }

    public function create(string $responseClass, ?object $instanceToPopulate = null): array
    {
        $this->lastInstance = $instanceToPopulate;

        return $this->responseFormat;
    }

    public function getLastInstance(): ?object
    {
        return $this->lastInstance;
    }
}
