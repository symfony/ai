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

use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Agent\Guardrail\GuardrailResult;
use Symfony\AI\Agent\Guardrail\InputGuardrailInterface;
use Symfony\AI\Agent\Guardrail\OutputGuardrailInterface;
use Symfony\AI\Agent\Input;
use Symfony\AI\Agent\Output;
use Symfony\AI\Platform\Message\UserMessage;

/**
 * Scans input and output text against user-defined regex patterns.
 *
 * This scanner can be used to detect custom patterns such as banned topics,
 * restricted keywords, or domain-specific injection techniques.
 *
 * @author Abderrahman Daif <daif.abderrahman@gmail.com>
 */
final class RegexScanner implements InputGuardrailInterface, OutputGuardrailInterface
{
    private const SCANNER_NAME = 'regex';

    /**
     * @var list<string>
     */
    private readonly array $patterns;

    /**
     * @param list<string> $patterns regex patterns to match against
     * @param string       $reason   the reason message when a pattern matches
     */
    public function __construct(
        array $patterns,
        private readonly string $reason = 'Content matched a restricted pattern.',
    ) {
        foreach ($patterns as $pattern) {
            if (false === @preg_match($pattern, '')) {
                throw new InvalidArgumentException(\sprintf('Invalid regex pattern: "%s".', $pattern));
            }
        }

        $this->patterns = $patterns;
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

            $result = $this->scan($text);

            if ($result->isTriggered()) {
                return $result;
            }
        }

        return GuardrailResult::pass();
    }

    public function validateOutput(Output $output): GuardrailResult
    {
        $content = $output->getResult()->getContent();

        if (!\is_string($content)) {
            return GuardrailResult::pass();
        }

        return $this->scan($content);
    }

    private function scan(string $text): GuardrailResult
    {
        foreach ($this->patterns as $pattern) {
            if (1 === preg_match($pattern, $text)) {
                return GuardrailResult::block(
                    self::SCANNER_NAME,
                    $this->reason,
                    1.0,
                );
            }
        }

        return GuardrailResult::pass();
    }
}
