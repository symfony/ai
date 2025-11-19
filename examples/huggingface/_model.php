<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\HuggingFace\ApiClient;
use Symfony\AI\Platform\Bridge\HuggingFace\Command\ModelInfoCommand;
use Symfony\AI\Platform\Bridge\HuggingFace\Command\ModelListCommand;
use Symfony\Component\Console\Application;

require_once dirname(__DIR__).'/bootstrap.php';

$apiClient = new ApiClient(http_client());

$app = new Application('Hugging Face Model Commands');
$app->addCommands([
    new ModelListCommand($apiClient),
    new ModelInfoCommand($apiClient),
]);

$app->run();
