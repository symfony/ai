<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Guardrail\Scanner;

use Symfony\AI\Agent\Guardrail\GuardrailResult;
use Symfony\AI\Agent\Guardrail\InputGuardrailInterface;
use Symfony\AI\Agent\Input;
use Symfony\AI\Platform\Message\UserMessage;

/**
 * Detects common prompt injection patterns in user input.
 *
 * Scans user messages for known prompt injection techniques such as
 * role overrides, instruction manipulation, and system prompt extraction attempts.
 *
 * @author Abderrahman Daif <daif.abderrahman@gmail.com>
 */
final class PromptInjectionScanner implements InputGuardrailInterface
{
    private const SCANNER_NAME = 'prompt_injection';

    /**
     * Patterns that indicate prompt injection attempts.
     * Each pattern is a case-insensitive regex.
     *
     * @var list<array{pattern: string, description: string}>
     */
    private const DEFAULT_PATTERNS = [
        ['pattern' => '/ignore\s+(all\s+)?(previous|prior|above|earlier)\s+(instructions|prompts|rules|directives)/i', 'description' => 'Instruction override attempt'],
        ['pattern' => '/disregard\s+(all\s+)?(previous|prior|above|earlier)\s+(instructions|prompts|rules|directives)/i', 'description' => 'Instruction override attempt'],
        ['pattern' => '/forget\s+(all\s+)?(previous|prior|above|earlier)\s+(instructions|prompts|rules|directives)/i', 'description' => 'Instruction override attempt'],
        ['pattern' => '/you\s+are\s+now\s+(?:a|an|the|in)\s+/i', 'description' => 'Role hijacking attempt'],
        ['pattern' => '/(?:new|updated|revised)\s+(?:system\s+)?instructions?\s*:/i', 'description' => 'System prompt injection'],
        ['pattern' => '/act\s+as\s+(?:if\s+)?(?:you\s+(?:are|were)\s+)?(?:a|an|the)\s+/i', 'description' => 'Role hijacking attempt'],
        ['pattern' => '/(?:reveal|show|display|print|output|repeat|echo)\s+(?:your\s+)?(?:system\s+)?(?:prompt|instructions|rules|directives)/i', 'description' => 'System prompt extraction attempt'],
        ['pattern' => '/what\s+(?:are|is|were)\s+your\s+(?:system\s+)?(?:prompt|instructions|rules|directives)/i', 'description' => 'System prompt extraction attempt'],
        ['pattern' => '/\bDAN\b.*\bjailbreak/i', 'description' => 'Known jailbreak pattern (DAN)'],
        ['pattern' => '/\[\s*(?:SYSTEM|INST|INSTRUCTION)\s*\]/i', 'description' => 'Fake system tag injection'],
        ['pattern' => '/<\|(?:im_start|system|endoftext)\|>/i', 'description' => 'Special token injection'],
    ];

    /**
     * @var list<array{pattern: string, description: string}>
     */
    private readonly array $patterns;

    /**
     * @param list<array{pattern: string, description: string}> $additionalPatterns
     */
    public function __construct(
        array $additionalPatterns = [],
        private readonly bool $includeDefaults = true,
    ) {
        if ($this->includeDefaults) {
            $this->patterns = array_merge(self::DEFAULT_PATTERNS, $additionalPatterns);
        } else {
            $this->patterns = $additionalPatterns;
        }
    }

    public function validateInput(Input $input): GuardrailResult
    {
        foreach ($input->getMessageBag()->getMessages() as $message) {
            if (!$message instanceof UserMessage) {
                continue;
            }

            $text = $message->asText();

            if (null === $text) {
                continue;
            }

            foreach ($this->patterns as $patternEntry) {
                if (1 === preg_match($patternEntry['pattern'], $text)) {
                    return GuardrailResult::block(
                        self::SCANNER_NAME,
                        $patternEntry['description'],
                        1.0,
                    );
                }
            }
        }

        return GuardrailResult::pass();
    }
}
