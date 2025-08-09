<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Ollama;

use Symfony\AI\Platform\Bridge\Ollama\Contract\OllamaContract;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Contract\ResultExtractor\StreamResultExtractor;
use Symfony\AI\Platform\Contract\ResultExtractor\TextResultExtractor;
use Symfony\AI\Platform\Contract\ResultExtractor\ToolCallResultExtractor;
use Symfony\AI\Platform\Contract\ResultExtractor\VectorResultExtractor;
use Symfony\AI\Platform\Platform;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class PlatformFactory
{
    public static function create(
        string $hostUrl = 'http://localhost:11434',
        ?HttpClientInterface $httpClient = null,
        ?Contract $contract = null,
    ): Platform {
        $httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);

        return new Platform(
            [new ModelClient($httpClient, $hostUrl)],
            [Contract\ResultConverter::create([
                new TextResultExtractor('$.message.content'),
                new ToolCallResultExtractor(
                    '$.message.tool_calls',
                    '$.message.tool_calls[*].function.id',
                    '$.message.tool_calls[*].function.name',
                    '$.message.tool_calls[*].function.arguments',
                ),
                new StreamResultExtractor(
                    '$.message.content',
                    '$.message.tool_calls',
                    '$.done_reason',
                ),
                new VectorResultExtractor('$.embeddings[*]'),
            ])],
            $contract ?? OllamaContract::create(),
        );
    }
}
