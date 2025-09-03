<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Replicate;

use Symfony\AI\Platform\Bridge\Replicate\Contract\LlamaMessageBagNormalizer;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Platform;
use Symfony\Component\Clock\Clock;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class PlatformFactory
{
    public static function create(
        #[\SensitiveParameter] string $apiKey,
        ?HttpClientInterface $httpClient = null,
        ?Contract $contract = null,
    ): Platform {
        return new Platform(
            [new LlamaModelClient(new Client($httpClient ?? HttpClient::create(), new Clock(), $apiKey))],
            [new LlamaResultConverter()],
            $contract ?? Contract::create(new LlamaMessageBagNormalizer()),
        );
    }
}
