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
    /**
     * @param array<mixed> $responseFormat
     */
    public function __construct(
        private readonly array $responseFormat = [],
    ) {
    }

    public function create(string|object $response): array
    {
        return $this->responseFormat;
    }
}
