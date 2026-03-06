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
use Symfony\AI\Agent\Exception\GuardrailException;
use Symfony\AI\Agent\Guardrail\GuardrailOutputProcessor;
use Symfony\AI\Agent\Guardrail\Scanner\RegexScanner;
use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('OPENAI_API_KEY'), http_client());

// Scenario: An internal company assistant that answers HR and onboarding questions.
// Even though the LLM is instructed to help, it may inadvertently include real-looking
// PII (email addresses, phone numbers) in generated responses — for example when
// composing onboarding checklists, sample org charts, or employee directories.
// The output guardrail prevents PII from reaching the end user (OWASP LLM02:2025).
$guardrailProcessor = new GuardrailOutputProcessor([
    new RegexScanner(
        patterns: [
            '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/',   // Email address
            '/\b(?:\+?\d{1,3}[-.\s]?)?\(?\d{3}\)?[-.\s]?\d{3}[-.\s]?\d{4}\b/', // Phone number
        ],
        reason: 'Response contains PII (email or phone number).',
    ),
]);

$agent = new Agent($platform, 'gpt-5-mini', outputProcessors: [$guardrailProcessor]);

// The assistant will naturally generate realistic contact details for the employee
// record, which triggers the output guardrail — demonstrating how PII leaks happen
// even without malicious intent.
$messages = new MessageBag(
    Message::forSystem(<<<SYSTEM
        You are an HR onboarding assistant for Acme Corp.
        When asked, generate realistic employee records with all standard fields.
        SYSTEM),
    Message::ofUser('Create a complete onboarding record for our new hire Jane Smith in the Engineering department, including her work email, phone number, manager, and start date.'),
);

try {
    $result = $agent->call($messages);
    output()->writeln($result->getContent());
} catch (GuardrailException $e) {
    $r = $e->getGuardrailResult();
    output()->writeln(sprintf(
        '<error>Output blocked</error> by <comment>%s</comment> (score: %.2f): %s',
        $r->getScanner(),
        $r->getScore(),
        $r->getReason(),
    ));
}
