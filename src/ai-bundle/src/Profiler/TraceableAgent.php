<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AiBundle\Profiler;

use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Policy\InputPolicyInterface;
use Symfony\AI\Agent\Policy\OutputPolicyInterface;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 *
 * @phpstan-type AgentData array{
 *     messages: MessageBag,
 *     options: array<string, mixed>,
 *     policies: InputPolicyInterface[]|OutputPolicyInterface[],
 * }
 */
final class TraceableAgent implements AgentInterface, ResetInterface
{
    /**
     * @var array<int, array{
     *     messages: MessageBag,
     *     options: array<string, mixed>,
     *     policies: InputPolicyInterface[]|OutputPolicyInterface[],
     * }>
     */
    public array $calls = [];

    public function __construct(
        private readonly AgentInterface $agent,
    ) {
    }

    public function call(MessageBag $messages, array $options = [], array $policies = []): ResultInterface
    {
        $this->calls[] = [
            'messages' => $messages,
            'options' => $options,
            'policies' => $policies,
        ];

        return $this->agent->call($messages, $options, $policies);
    }

    public function getName(): string
    {
        return $this->agent->getName();
    }

    public function reset(): void
    {
        $this->calls = [];
    }
}
