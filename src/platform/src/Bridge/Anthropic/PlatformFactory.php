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

use Symfony\AI\Platform\Bridge\Anthropic\Contract\AnthropicContract;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Contract\ResultExtractor\StreamResultExtractor;
use Symfony\AI\Platform\Contract\ResultExtractor\TextResultExtractor;
use Symfony\AI\Platform\Contract\ResultExtractor\ToolCallResultExtractor;
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
        ?HttpClientInterface $httpClient = null,
        ?Contract $contract = null,
    ): Platform {
        $httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);

        return new Platform(
            [new ModelClient($httpClient, $apiKey)],
            [Contract\ResultConverter::create([
                new TextResultExtractor('$.content[?@.type == "text"].text'),
                new ToolCallResultExtractor(
                    '$.content[?@.type == "tool_use"]',
                    '$.content[?@.type == "tool_use"].id',
                    '$.content[?@.type == "tool_use"].name',
                    '$.content[?@.type == "tool_use"].input',
                ),
                new StreamResultExtractor(
                    '$.delta.text',
                    '$.delta[?@.type == "tool_use"]',
                    '$.delta.stop_reason',
                ),
            ])],
            $contract ?? AnthropicContract::create(),
        );
    }
}
