<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\ModelsLab;

use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\Platform;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Adhik Joshi <adhik@modelslab.com>
 */
final class PlatformFactory
{
    public static function create(
        #[\SensitiveParameter] string $apiKey,
        ?HttpClientInterface $httpClient = null,
        ModelCatalogInterface $modelCatalog = new ModelCatalog(),
        ?EventDispatcherInterface $eventDispatcher = null,
    ): Platform {
        $httpClient ??= HttpClient::create();

        return new Platform(
            [new ModelsLabClient($httpClient, $apiKey)],
            [new ModelsLabResultConverter()],
            $modelCatalog,
            null,
            $eventDispatcher,
        );
    }
}
