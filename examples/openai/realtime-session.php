<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\OpenAi\Factory;
use Symfony\AI\Platform\Result\RealtimeSessionResult;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('OPENAI_API_KEY'), http_client());

// A realtime session is created server side and returns an ephemeral client
// secret. That secret is what you hand to a browser or mobile client, which
// then opens the WebRTC or WebSocket connection to OpenAI itself.
$result = $platform->invoke('gpt-4o-realtime-preview', 'You are a friendly assistant. Keep your answers short.', [
    'voice' => 'alloy',
]);

$session = $result->getResult();

assert($session instanceof RealtimeSessionResult);

output()->writeln(sprintf('Session:    %s', $session->getId()));
output()->writeln(sprintf('Model:      %s', $session->getModel()));
output()->writeln(sprintf('Voice:      %s', $session->getVoice() ?? 'n/a'));
output()->writeln(sprintf('Modalities: %s', implode(', ', $session->getModalities())));
output()->writeln(sprintf('Expires at: %s', date('Y-m-d H:i:s', $session->getExpiresAt())));

// Never log or print the client secret in a real application: anyone holding it
// can talk to the model on your account until it expires.
output()->writeln(sprintf('Secret:     %s…', substr($session->getClientSecret(), 0, 8)));
