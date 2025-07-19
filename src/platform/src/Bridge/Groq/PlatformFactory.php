<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Groq;

use Symfony\AI\Platform\Bridge\Groq\Llama\ModelClient as LlamaModelClient;
use Symfony\AI\Platform\Bridge\Groq\Llama\ResponseConverter as LlamaResponseConverter;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Platform;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 * @author Dave Hulbert <dave1010@gmail.com>
 */
final readonly class PlatformFactory
{
    public static function create(
        #[\SensitiveParameter]
        string $apiKey,
        ?HttpClientInterface $httpClient = null,
        ?Contract $contract = null,
    ): Platform {
        $httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);

        return new Platform(
            [
                new LlamaModelClient($httpClient, $apiKey),
            ],
            [
                new LlamaResponseConverter(),
            ],
            $contract ?? Contract::create(),
        );
    }
}
