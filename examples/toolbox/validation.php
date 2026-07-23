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
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\Agent\Toolbox\Event\ToolCallArgumentsResolved;
use Symfony\AI\Agent\Toolbox\EventListener\ValidateToolCallArgumentsListener;
use Symfony\AI\Agent\Toolbox\FaultTolerantToolbox;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\Bridge\OpenAi\Factory;
use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Validator\Constraints as Assert;

require_once dirname(__DIR__).'/bootstrap.php';

#[AsTool('get_country_info', 'Get information about a country by its ISO 3166-1 alpha-2 code')]
final class GetCountryInfo
{
    public function __invoke(CountryQuery $query): string
    {
        return sprintf('The country with code "%s" is a beautiful place!', $query->countryCode);
    }
}

final class CountryQuery
{
    public function __construct(
        #[Assert\Regex(pattern: '/^[A-Z]{2}$/', message: 'Must be a valid ISO 3166-1 alpha-2 country code (e.g. "DE", "US", "FR").')]
        public string $countryCode,
    ) {
    }
}

// A #[Schema] attribute is enough to validate scalar parameters, no wrapper DTO needed.
#[AsTool('get_order', 'Get the status of an order by its reference')]
final class GetOrder
{
    public function __invoke(
        #[Schema(pattern: '^ORD-\d{4}-\d{4}$', description: 'Order reference, e.g. "ORD-2026-0042"')]
        string $reference,
    ): string {
        return sprintf('Order "%s" is being processed.', $reference);
    }
}

$platform = Factory::createPlatform(env('OPENAI_API_KEY'), http_client());

$eventDispatcher = new EventDispatcher();
$eventDispatcher->addListener(ToolCallArgumentsResolved::class, new ValidateToolCallArgumentsListener());

// FaultTolerantToolbox turns the InvalidToolCallArgumentsException thrown for a badly formatted
// argument into a readable error message returned to the LLM, instead of an uncaught exception.
$toolbox = new FaultTolerantToolbox(
    new Toolbox([new GetCountryInfo(), new GetOrder()], logger: logger(), eventDispatcher: $eventDispatcher),
);
$processor = new AgentProcessor($toolbox, eventDispatcher: $eventDispatcher);
$agent = new Agent($platform, 'gpt-5-mini', [$processor], [$processor]);

$messages = new MessageBag(Message::ofUser('Use the tool to get info about Finland.'));
$result = $agent->call($messages);

echo $result->getContent().\PHP_EOL;

$messages = new MessageBag(Message::ofUser('Look up the order with reference "ORD-2026-0042".'));
$result = $agent->call($messages);

echo $result->getContent().\PHP_EOL;

// The LLM is instructed to use an invalid reference on purpose, to show the tool call being rejected.
$messages = new MessageBag(Message::ofUser('Look up the order using exactly this reference, unmodified: "not-a-valid-reference".'));
$result = $agent->call($messages);

echo $result->getContent().\PHP_EOL;
