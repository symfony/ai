<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Test\Replay;

use Symfony\AI\Platform\Exception\InvalidArgumentException;

/**
 * Replaces secrets and personal data in a recorded request body.
 *
 * Cassettes are meant to be committed, so whatever a prompt carried at record time ends up in the
 * repository: an address the user typed, a phone number quoted back from a ticket, occasionally an
 * API key pasted into a message by mistake. Sanitizing headers is not enough once the body is
 * stored verbatim.
 *
 * Redaction is therefore on by default rather than opt-in: the failure mode is silent and only
 * surfaces once the cassette is public, which is too late to be a useful signal.
 *
 * Rules come in three tiers:
 *
 *  - credentials are always applied and cannot be switched off;
 *  - personal data is applied by default but can be disabled, because a test may exist precisely to
 *    exercise how personal data flows through a prompt;
 *  - extra patterns cover identifiers that only make sense in one codebase - a customer reference,
 *    an internal ticket format - which no framework default can guess.
 *
 * @author Miguel Sampedro <264189149+MikiBuilder@users.noreply.github.com>
 */
final class BodyRedactor
{
    /**
     * Provider keys, signed tokens and cloud credentials. Never optional: a leaked key is an
     * incident regardless of what the test was doing.
     *
     * @var array<string, string>
     */
    private const CREDENTIAL_PATTERNS = [
        '/\b(?:sk|pk|rk)-[A-Za-z0-9_\-]{16,}\b/' => '[redacted-key]',
        '/\bgsk_[A-Za-z0-9]{20,}\b/' => '[redacted-key]',
        '/\bAIza[A-Za-z0-9_\-]{30,}\b/' => '[redacted-key]',
        '/\bAKIA[0-9A-Z]{16}\b/' => '[redacted-key]',
        '/\bgh[pousr]_[A-Za-z0-9]{30,}\b/' => '[redacted-key]',
        '/\beyJ[A-Za-z0-9_\-]{10,}\.[A-Za-z0-9_\-]{10,}\.[A-Za-z0-9_\-]{10,}\b/' => '[redacted-jwt]',
        '/\bBearer\s+[A-Za-z0-9._\-]{20,}/i' => 'Bearer [redacted]',
    ];

    /**
     * Personal data. Deliberately conservative: an unanchored phone pattern will happily eat
     * timestamps, order numbers and identifiers out of a legitimate payload, which corrupts the
     * recording it was meant to protect.
     *
     * @var array<string, string>
     */
    private const PII_PATTERNS = [
        '/\b[\w.+-]+@[\w-]+\.[\w.]{2,}\b/' => '[redacted-email]',
        '/(?<![\w.])\+\d{1,3}[ .-]?\(?\d{1,4}\)?(?:[ .-]?\d{2,4}){2,4}(?![\w.])/' => '[redacted-phone]',
    ];

    /** @var array<string, string> */
    private readonly array $patterns;

    /**
     * @param bool                  $pii           whether to redact personal data on top of credentials
     * @param array<string, string> $extraPatterns additional `pattern => replacement` pairs, applied last
     *
     * @throws InvalidArgumentException if a supplied pattern is not a valid regular expression
     */
    public function __construct(
        bool $pii = true,
        array $extraPatterns = [],
    ) {
        foreach ($extraPatterns as $pattern => $replacement) {
            if (false === @preg_match($pattern, '')) {
                throw new InvalidArgumentException(\sprintf('The redaction pattern "%s" is not a valid regular expression.', $pattern));
            }
        }

        $this->patterns = $extraPatterns + ($pii ? self::PII_PATTERNS : []) + self::CREDENTIAL_PATTERNS;
    }

    /**
     * Returns a redactor that only replaces credentials.
     *
     * Useful when the personal data in a payload is the thing under test and replacing it would
     * defeat the recording.
     */
    public static function credentialsOnly(): self
    {
        return new self(pii: false);
    }

    /**
     * Redacts a request body, walking arrays so a JSON payload is covered field by field.
     *
     * Keys are left untouched: they are part of the API contract, and rewriting them would change
     * the shape of the recorded request.
     */
    public function redact(mixed $body): mixed
    {
        if (\is_string($body)) {
            return $this->redactString($body);
        }

        if (\is_array($body)) {
            return array_map($this->redact(...), $body);
        }

        return $body;
    }

    private function redactString(string $value): string
    {
        foreach ($this->patterns as $pattern => $replacement) {
            $value = preg_replace($pattern, $replacement, $value) ?? $value;
        }

        return $value;
    }
}
