<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\ModelsDev\PlatformFactory;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(
    provider: 'google',
    apiKey: env('GEMINI_API_KEY'),
    httpClient: http_client(),
);

$text = <<<TEXT
    The Symfony framework is a set of reusable PHP components and a PHP framework
    for building web applications, APIs, microservices and web services.
    TEXT;

$result = $platform->invoke('gemini-embedding-001', $text);

print_vectors($result);
