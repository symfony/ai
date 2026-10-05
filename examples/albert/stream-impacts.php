<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Albert\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('ALBERT_API_KEY'), 'https://albert.api.etalab.gouv.fr', http_client());

$messages = new MessageBag(
    Message::forSystem('You are a helpful assistant of the French public administration.'),
    Message::ofUser('Why does the French public administration run its own AI gateway? Answer in one sentence.'),
);

$result = $platform->invoke('openweight-small', $messages, ['stream' => true]);

foreach ($result->asStream() as $delta) {
    if ($delta instanceof TextDelta) {
        echo $delta;
    }
}

echo \PHP_EOL.\PHP_EOL;

// Both are only known once the stream is fully consumed: the provider reports them on its
// trailing chunks, which the stream listeners promote to the result metadata.
print_token_usage($result->getMetadata()->get('token_usage'));

// Next to the token usage, Albert reports the environmental footprint of the call as an
// estimated energy consumption and greenhouse gas emission.
$impacts = $result->getMetadata()->get('impacts');

echo \PHP_EOL;
echo 'Environmental impact'.\PHP_EOL;
echo sprintf('  Energy:    %.3e kWh', $impacts['kWh']).\PHP_EOL;
echo sprintf('  Emission:  %.3e kgCO2eq', $impacts['kgCO2eq']).\PHP_EOL;
