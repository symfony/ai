<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenRouter;

use Symfony\AI\Platform\Bridge\Gemini\Contract\AssistantMessageNormalizer;
use Symfony\AI\Platform\Bridge\Gemini\Contract\MessageBagNormalizer;
use Symfony\AI\Platform\Bridge\Gemini\Contract\UserMessageNormalizer;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Platform;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author rglozman
 */
final class PlatformFactory
{
    public static function create(
        #[\SensitiveParameter] string $apiKey,
        ?HttpClientInterface $httpClient = null,
        ?Contract $contract = null,
    ): Platform {
        $httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);

        return new Platform(
            [new ModelClient($httpClient, $apiKey)],
            [new ResultConverter()],
            $contract ?? Contract::create(
                new AssistantMessageNormalizer(),
                new MessageBagNormalizer(),
                new UserMessageNormalizer(),
            ),
        );
    }
}
