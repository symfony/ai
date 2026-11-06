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
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\Bridge\Anthropic\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

#[AsTool('clock', 'Get the current date and time')]
final class Clock
{
    public function __invoke(): string
    {
        return date('Y-m-d H:i:s');
    }
}

#[AsTool('convert_currency', 'Convert an amount from one currency into another', deferred: true)]
final class CurrencyConverter
{
    public function __invoke(float $amount, string $from, string $to): string
    {
        return sprintf('%.2f %s are %.2f %s.', $amount, $from, $amount * 1.08, $to);
    }
}

#[AsTool('get_invoice', 'Fetch an invoice by its number', deferred: true)]
final class InvoiceFetcher
{
    public function __invoke(string $number): string
    {
        return sprintf('Invoice %s over 1337.00 EUR, issued to ACME Inc.', $number);
    }
}

#[AsTool('release_train', 'Trigger the release train of a deployment pipeline', deferred: true)]
final class ReleaseTrain
{
    public function __invoke(string $pipeline): string
    {
        return sprintf('Release train of pipeline "%s" started.', $pipeline);
    }
}

$platform = Factory::createPlatform(env('ANTHROPIC_API_KEY'), httpClient: http_client());

// the deferred tools are only sent to the model once Anthropic's tool search found them
$toolbox = new Toolbox([new Clock(), new CurrencyConverter(), new InvoiceFetcher(), new ReleaseTrain()], logger: logger());
$agent = new Agent($platform, 'claude-sonnet-4-5', toolbox: $toolbox);

$messages = new MessageBag(Message::ofUser('How much is the invoice 2024-042 in US dollars?'));
$result = $agent->call($messages, ['max_tokens' => 2048]);

echo $result->getContent().\PHP_EOL;
