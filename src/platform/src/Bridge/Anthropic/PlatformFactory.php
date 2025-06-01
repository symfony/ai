<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Anthropic;

use Symfony\AI\Platform\Bridge\Anthropic\Contract\AssistantMessageNormalizer;
use Symfony\AI\Platform\Bridge\Anthropic\Contract\DocumentNormalizer;
use Symfony\AI\Platform\Bridge\Anthropic\Contract\DocumentUrlNormalizer;
use Symfony\AI\Platform\Bridge\Anthropic\Contract\ImageNormalizer;
use Symfony\AI\Platform\Bridge\Anthropic\Contract\ImageUrlNormalizer;
use Symfony\AI\Platform\Bridge\Anthropic\Contract\MessageBagNormalizer;
use Symfony\AI\Platform\Bridge\Anthropic\Contract\ToolCallMessageNormalizer;
use Symfony\AI\Platform\Bridge\Anthropic\Contract\ToolNormalizer;
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
        #[\SensitiveParameter]
        string $apiKey,
        string $version = '2023-06-01',
        ?HttpClientInterface $httpClient = null,
    ): Platform {
        $httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);

        return new Platform(
            [new ModelClient($httpClient, $apiKey, $version)],
            [new ResponseConverter()],
            Contract::create(
                new AssistantMessageNormalizer(),
                new DocumentNormalizer(),
                new DocumentUrlNormalizer(),
                new ImageNormalizer(),
                new ImageUrlNormalizer(),
                new MessageBagNormalizer(),
                new ToolCallMessageNormalizer(),
                new ToolNormalizer(),
            )
        );
    }
}
