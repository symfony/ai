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

final readonly class ConfigurableResponseFormatFactory implements ResponseFormatFactoryInterface
{
    /**
     * @param array<mixed> $responseFormat
     */
    public function __construct(
        private array $responseFormat = [],
    ) {
    }

    public function create(string $responseClass): array
    {
        return $this->responseFormat;
    }
}
