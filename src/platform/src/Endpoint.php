<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform;

use Symfony\AI\Platform\Exception\InvalidArgumentException;

/**
 * Identifies one contract a model speaks (e.g. "anthropic.messages",
 * "openai.chat_completions", "openai.responses") together with any
 * contract-specific defaults that should be folded in at invocation time.
 *
 * The contract identifier is a free-form string by design: bridges and
 * dynamic catalogs can register new ones without core changes. Built-in
 * identifiers live as constants on the corresponding ContractHandler
 * implementation (see {@see EndpointClientInterface::endpoint()}).
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Endpoint
{
    /**
     * @param non-empty-string     $contract
     * @param array<string, mixed> $defaults
     */
    public function __construct(
        private readonly string $contract,
        private readonly array $defaults = [],
    ) {
        if ('' === trim($contract)) {
            throw new InvalidArgumentException('Endpoint contract cannot be empty.');
        }
    }

    /**
     * @return non-empty-string
     */
    public function getContract(): string
    {
        return $this->contract;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDefaults(): array
    {
        return $this->defaults;
    }
}
