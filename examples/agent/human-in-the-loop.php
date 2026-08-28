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
use Symfony\AI\Agent\Approval\ApprovalDecision;
use Symfony\AI\Agent\Approval\ApprovalManager;
use Symfony\AI\Agent\Approval\ApprovalPendingResult;
use Symfony\AI\Agent\Approval\Attribute\RequiresApproval;
use Symfony\AI\Agent\Approval\Checkpoint\CheckpointSigner;
use Symfony\AI\Agent\Approval\Checkpoint\InMemoryCheckpointStore;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\Bridge\OpenAi\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

#[AsTool('transfer_funds', 'Transfers money from the user account to a destination IBAN')]
#[RequiresApproval(prompt: 'Transfer ${amount} to account #{iban}', roles: ['ROLE_FINANCE_ADMIN'])]
final class PaymentService
{
    public function __invoke(string $iban, float $amount): string
    {
        return sprintf('Successfully wired $%0.2f to account %s. Transaction ID: TX-%d', $amount, $iban, random_int(10000, 99999));
    }
}

$platform = Factory::createPlatform(env('OPENAI_API_KEY'), http_client());

$store = new InMemoryCheckpointStore();
$signer = new CheckpointSigner('app-secret-demo-key');
$approvalManager = new ApprovalManager([], $store, $signer);

$toolbox = new Toolbox([new PaymentService()], logger: logger());
$agent = new Agent($platform, 'gpt-4o-mini', toolbox: $toolbox, approvalManager: $approvalManager);

$messages = new MessageBag(
    Message::forSystem('You are an AI financial assistant. Help the user initiate money transfers safely.'),
    Message::ofUser('Please transfer $250.00 to IBAN DE89370400440532013000'),
);

output()->writeln('<info>1. Initiating transfer request...</info>');
$result = $agent->call($messages)->getResult();

if ($result instanceof ApprovalPendingResult) {
    output()->writeln('<comment>2. Execution paused for Human Approval!</comment>');
    output()->writeln(sprintf('   Prompt: %s', $result->getPrompt()));
    output()->writeln(sprintf('   Roles required: %s', implode(', ', $result->getRoles())));
    output()->writeln(sprintf('   Checkpoint ID: %s', $result->getCheckpoint()->getId()));
    output()->writeln(sprintf('   Signed Token: %s', substr($result->getToken() ?? '', 0, 40).'...'));

    output()->writeln('<info>3. Human Admin approves the transfer with positive feedback...</info>');
    $finalResult = $agent->resume($result->getCheckpoint(), ApprovalDecision::approve('Approved by CFO'));

    output()->writeln('<info>4. Final Agent Response:</info>');
    output()->writeln($finalResult->getContent());
} else {
    output()->writeln($result->getContent());
}
