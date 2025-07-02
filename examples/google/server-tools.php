<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use PhpLlm\LlmChain\Chain\Chain;
use PhpLlm\LlmChain\Chain\Toolbox\ChainProcessor;
use PhpLlm\LlmChain\Chain\Toolbox\Tool\Clock;
use PhpLlm\LlmChain\Chain\Toolbox\Toolbox;
use PhpLlm\LlmChain\Platform\Bridge\Google\Gemini;
use PhpLlm\LlmChain\Platform\Bridge\Google\PlatformFactory;
use PhpLlm\LlmChain\Platform\Message\Message;
use PhpLlm\LlmChain\Platform\Message\MessageBag;
use Symfony\Component\Dotenv\Dotenv;

require_once dirname(__DIR__, 2).'/vendor/autoload.php';
(new Dotenv())->loadEnv(dirname(__DIR__, 2).'/.env');

if (!$_ENV['GOOGLE_API_KEY']) {
    echo 'Please set the GOOGLE_API_KEY environment variable.'.\PHP_EOL;
    exit(1);
}

$platform = PlatformFactory::create($_ENV['GOOGLE_API_KEY']);

// Available server-side tools as of 2025-06-28: url_context, google_search, code_execution
$llm = new Gemini('gemini-2.5-pro-preview-03-25', ['server_tools' => ['url_context' => true], 'temperature' => 1.0]);

$toolbox = Toolbox::create(new Clock());
$processor = new ChainProcessor($toolbox);
$chain = new Chain($platform, $llm);

$messages = new MessageBag(
    Message::ofUser(
        <<<'PROMPT'
            What was the 12 month Euribor rate a week ago based on https://www.euribor-rates.eu/en/current-euribor-rates/4/euribor-rate-12-months/
            PROMPT,
    ),
);

$response = $chain->call($messages);

echo $response->getContent().\PHP_EOL;
