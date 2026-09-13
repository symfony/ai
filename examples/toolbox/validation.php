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

// ValidateToolCallArgumentsListener validates both kinds of tool parameters in the same call:
// - $reference is a scalar, validated through its #[Schema] constraint;
// - $destination is an object, validated through the Symfony Validator constraints on its properties.
#[AsTool('get_order', 'Get the status of an order by its reference and shipping destination')]
final class GetOrder
{
    public function __invoke(
        #[Schema(pattern: '^ORD-\d{4}-\d{4}$', description: 'Order reference, e.g. "ORD-2026-0042"')]
        string $reference,
        ShippingAddress $destination,
    ): string {
        return sprintf('Order "%s" is being shipped to %s, %s.', $reference, $destination->city, $destination->countryCode);
    }
}

final class ShippingAddress
{
    public function __construct(
        #[Assert\Length(max: 255)]
        public string $city,
        #[Assert\Regex(pattern: '/^[A-Z]{2}$/', message: 'Must be a valid ISO 3166-1 alpha-2 country code (e.g. "DE", "US", "FR").')]
        public string $countryCode,
    ) {
    }
}

$platform = Factory::createPlatform(env('OPENAI_API_KEY'), http_client());

$eventDispatcher = new EventDispatcher();
$eventDispatcher->addListener(ToolCallArgumentsResolved::class, new ValidateToolCallArgumentsListener());

// FaultTolerantToolbox turns the InvalidToolCallArgumentsException thrown for a rejected call into a
// readable error message returned to the LLM, instead of an uncaught exception.
$toolbox = new FaultTolerantToolbox(
    new Toolbox([new GetOrder()], logger: logger(), eventDispatcher: $eventDispatcher),
);
$agent = new Agent($platform, 'gpt-5-mini', toolbox: $toolbox, eventDispatcher: $eventDispatcher);

$messages = new MessageBag(Message::ofUser('Look up the order with reference "ORD-2026-0042", shipping to Berlin, DE.'));
$result = $agent->call($messages);

echo $result->asText().\PHP_EOL;

// The LLM is instructed to use a malformed reference on purpose, to show the #[Schema] pattern being enforced.
$messages = new MessageBag(Message::ofUser('Look up the order using exactly this reference, unmodified: "not-a-valid-reference", shipping to Berlin, DE.'));
$result = $agent->call($messages);

echo $result->asText().\PHP_EOL;
