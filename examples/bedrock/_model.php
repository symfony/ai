<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Bedrock\BedrockClient;
use Symfony\AI\Platform\Bridge\Bedrock\Command\ModelListCommand;
use Symfony\Component\Console\Application;

require_once dirname(__DIR__).'/bootstrap.php';

if (!isset($_SERVER['AWS_ACCESS_KEY_ID'], $_SERVER['AWS_SECRET_ACCESS_KEY'], $_SERVER['AWS_DEFAULT_REGION'])
) {
    echo 'Please set the AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY and AWS_DEFAULT_REGION environment variables.'.\PHP_EOL;
    exit(1);
}

$bedrockClient = new BedrockClient();

$app = new Application('Amazon Bedrock Model Commands');
$app->addCommands([
    new ModelListCommand($bedrockClient),
]);

$app->run();
