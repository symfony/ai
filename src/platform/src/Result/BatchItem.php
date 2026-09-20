<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Result;

use Symfony\AI\Platform\Exception\RuntimeException;

/**
 * One request of a batch, as the provider answered it: either the result or the reason there is none.
 *
 * Why there is none matters, and providers distinguish more than failure: a request the batch never
 * got to send, because it was canceled or expired, costs nothing and can simply be submitted again,
 * where a request that errored has to be fixed first. The wording is the bridge's to translate into a
 * {@see BatchItemCase}, the same split as `FinishReason` and `JobStatus`.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class BatchItem
{
    /**
     * @param string      $id  the identifier the request was submitted under
     * @param string|null $raw the untouched outcome reported by the provider, when it states one
     */
    private function __construct(
        private readonly string $id,
        private readonly BatchItemCase $case,
        private readonly ?string $raw,
        private readonly ?ResultInterface $result,
        private readonly ?string $error,
    ) {
    }

    public static function succeeded(string $id, ResultInterface $result, ?string $raw = null): self
    {
        return new self($id, BatchItemCase::SUCCEEDED, $raw, $result, null);
    }

    public static function errored(string $id, string $error, ?string $raw = null): self
    {
        return new self($id, BatchItemCase::ERRORED, $raw, null, $error);
    }

    public static function canceled(string $id, ?string $raw = null): self
    {
        return new self($id, BatchItemCase::CANCELED, $raw, null, 'The batch was canceled before this request was sent.');
    }

    public static function expired(string $id, ?string $raw = null): self
    {
        return new self($id, BatchItemCase::EXPIRED, $raw, null, 'The batch expired before this request was sent.');
    }

    public static function unknown(string $id, string $raw, ?string $error = null): self
    {
        return new self($id, BatchItemCase::UNKNOWN, $raw, null, $error);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getCase(): BatchItemCase
    {
        return $this->case;
    }

    public function getRaw(): ?string
    {
        return $this->raw;
    }

    public function is(BatchItemCase ...$cases): bool
    {
        return \in_array($this->case, $cases, true);
    }

    public function isSuccess(): bool
    {
        return BatchItemCase::SUCCEEDED === $this->case;
    }

    /**
     * @throws RuntimeException when the request produced no result, see {@see getError()}
     */
    public function getResult(): ResultInterface
    {
        return $this->result ?? throw new RuntimeException(\sprintf('The batch request "%s" did not produce a result: "%s"', $this->id, $this->error ?? 'unknown error'));
    }

    public function getError(): ?string
    {
        return $this->error;
    }
}
