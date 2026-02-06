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
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\Bridge\Anthropic\PlatformFactory;
use Symfony\AI\Platform\Contract\JsonSchema\Attribute\With;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\Serializer\Attribute\DiscriminatorMap;

require_once dirname(__DIR__).'/bootstrap.php';

// Setup and run
$platform = PlatformFactory::create(env('ANTHROPIC_API_KEY'), httpClient: http_client());

$navigatorTool = new NavigatorTool();
$toolbox = new Toolbox([$navigatorTool]);
$processor = new AgentProcessor($toolbox);
$agent = new Agent($platform, 'claude-sonnet-4', [$processor], [$processor]);

$messages = new MessageBag(
    Message::forSystem('You are a helpful navigation assistant. Help users navigate to different resources.'),
    Message::ofUser('Navigate to order number ORD-12345 with user responsible John Doe'),
);

$result = $agent->call($messages);

echo $result->getContent().\PHP_EOL;

// Tool that uses polymorphic interface as parameter
#[AsTool('navigator', 'Navigate to various resources based on filter criteria', method: 'navigate')]
final class NavigatorTool
{
    /**
     * @param Filterable $filter The filter to use for navigation (order or purchase_contract)
     */
    public function navigate(Filterable $filter): string
    {
        return match (true) {
            $filter instanceof OrderFilter => sprintf(
                'Navigating to order: %s (User: %s)',
                $filter->number ?? 'N/A',
                $filter->userResponsible ?? 'N/A'
            ),
            $filter instanceof PurchaseContractFilter => sprintf(
                'Navigating to purchase contract: %s (Subsidiary: %s)',
                $filter->contractNumber ?? 'N/A',
                $filter->subsidiary ?? 'N/A'
            ),
            default => 'Unknown filter type',
        };
    }
}

#[DiscriminatorMap(
    typeProperty: 'type',
    mapping: [
        'order' => OrderFilter::class,
        'purchase_contract' => PurchaseContractFilter::class,
    ]
)]
interface Filterable
{
}

final class OrderFilter implements Filterable
{
    public function __construct(
        #[With(const: 'order')]
        public string $type = 'order',
        public ?string $number = null,
        public ?string $userResponsible = null,
    ) {
    }
}

final class PurchaseContractFilter implements Filterable
{
    public function __construct(
        #[With(const: 'purchase_contract')]
        public string $type = 'purchase_contract',
        public ?string $contractNumber = null,
        public ?string $subsidiary = null,
    ) {
    }
}
