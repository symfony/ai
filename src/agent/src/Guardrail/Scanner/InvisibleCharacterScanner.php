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
 * Detects invisible or zero-width Unicode characters in user input.
 *
 * These characters can be used to hide instructions from human review
 * while still being processed by LLMs, making them a vector for
 * prompt injection attacks.
 *
 * @author Abderrahman Daif <daif.abderrahman@gmail.com>
 */
final class InvisibleCharacterScanner implements InputGuardrailInterface
{
    private const SCANNER_NAME = 'invisible_characters';

    /**
     * Unicode patterns for invisible/zero-width characters.
     *
     * @see https://unicode-explorer.com/articles/invisible-characters
     */
    private const INVISIBLE_PATTERN = '/[\x{200B}\x{200C}\x{200D}\x{200E}\x{200F}\x{2060}\x{2061}\x{2062}\x{2063}\x{2064}\x{FEFF}\x{00AD}\x{034F}\x{061C}\x{180E}\x{2028}\x{2029}\x{202A}\x{202B}\x{202C}\x{202D}\x{202E}\x{2066}\x{2067}\x{2068}\x{2069}\x{206A}\x{206B}\x{206C}\x{206D}\x{206E}\x{206F}]/u';

    /**
     * @param int $maxAllowed maximum number of invisible characters allowed before triggering
     */
    public function __construct(
        private readonly int $maxAllowed = 0,
    ) {
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

            $matchCount = preg_match_all(self::INVISIBLE_PATTERN, $text);

            if (false === $matchCount) {
                continue;
            }

            if ($matchCount > $this->maxAllowed) {
                return GuardrailResult::block(
                    self::SCANNER_NAME,
                    \sprintf('Detected %d invisible character(s) in user input.', $matchCount),
                    min(1.0, $matchCount / 10),
                );
            }
        }

        return GuardrailResult::pass();
    }
}
