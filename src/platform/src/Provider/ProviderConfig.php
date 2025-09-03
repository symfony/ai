<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Provider;

final readonly class ProviderConfig
{
    public function __construct(
        public string $provider,
        public string $baseUri,
        public ?string $apiKey,
        public array $options = [],
        public array $headers = [],
    ) {
    }
}
