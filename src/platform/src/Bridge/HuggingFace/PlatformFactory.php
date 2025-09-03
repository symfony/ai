<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\HuggingFace;

use Symfony\AI\Platform\Bridge\HuggingFace\Contract\FileNormalizer;
use Symfony\AI\Platform\Bridge\HuggingFace\Contract\MessageBagNormalizer;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Platform;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final readonly class PlatformFactory
{
    public static function create(
        #[\SensitiveParameter] string $apiKey,
        string $provider = Provider::HF_INFERENCE,
        ?HttpClientInterface $httpClient = null,
        ?Contract $contract = null,
    ): Platform {
        $httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);

        return new Platform(
            [new ModelClient($httpClient, $provider, $apiKey)],
            [new ResultConverter()],
            $contract ?? Contract::create(
                new FileNormalizer(),
                new MessageBagNormalizer(),
            ),
        );
    }
}
