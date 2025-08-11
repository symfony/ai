<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\Toolbox\AgentProcessor;
use Symfony\AI\Agent\Toolbox\Tool\Clock;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\Bridge\VertexAi\Gemini\Model;
use Symfony\AI\Platform\Bridge\VertexAi\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once __DIR__.'/bootstrap.php';

$platform = PlatformFactory::create(env('GOOGLE_CLOUD_LOCATION'), env('GOOGLE_CLOUD_PROJECT'), adc_aware_http_client());

$model = new Model(Model::GEMINI_2_5_PRO);

$toolbox = new Toolbox([new Clock()], logger: logger());
$processor = new AgentProcessor($toolbox);
$agent = new Agent($platform, $model, [$processor], [$processor], logger());

$content = file_get_contents('https://www.euribor-rates.eu/en/current-euribor-rates/4/euribor-rate-12-months/');
$messages = new MessageBag(
    Message::ofUser("Based on the following page content, what was the 12-month Euribor rate a week ago?\n\n".$content)
);

$result = $agent->call($messages);

echo $result->getContent().\PHP_EOL;
