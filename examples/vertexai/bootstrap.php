<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Google\Auth\ApplicationDefaultCredentials;
use Psr\Log\LoggerAwareInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

require_once dirname(__DIR__).'/bootstrap.php';

function adc_aware_http_client(): HttpClientInterface
{
    $credentials = ApplicationDefaultCredentials::getCredentials(['https://www.googleapis.com/auth/cloud-platform']);
    $httpClient = HttpClient::create([
        'auth_bearer' => $credentials->fetchAuthToken()['access_token'] ?? null,
    ]);

    if ($httpClient instanceof LoggerAwareInterface) {
        $httpClient->setLogger(logger());
    }

    return $httpClient;
}
