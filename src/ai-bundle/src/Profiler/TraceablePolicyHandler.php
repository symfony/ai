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

use Symfony\AI\Agent\Policy\InputPolicyInterface;
use Symfony\AI\Agent\Policy\OutputPolicyInterface;
use Symfony\AI\Agent\Policy\PolicyHandlerInterface;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 *
 * @phpstan-type PolicyHandlerData array{
 *    method: string,
 *    messages?: MessageBag,
 *    options?: array<string, mixed>,
 *    policy: string,
 * }
 */
final class TraceablePolicyHandler implements PolicyHandlerInterface, ResetInterface
{
    /**
     * @var array<int, array{
     *     method: string,
     *     messages?: MessageBag,
     *     options?: array<string, mixed>,
     *     policy: string,
     * }>
     */
    public array $calls = [];

    public function __construct(
        private readonly PolicyHandlerInterface $policyHandler,
    ) {
    }

    public function handle(MessageBag $messages, array $options, InputPolicyInterface|OutputPolicyInterface $policy): void
    {
        $this->calls[] = [
            'method' => 'handle',
            'messages' => $messages,
            'options' => $options,
            'policy' => $policy::class,
        ];

        $this->policyHandler->handle($messages, $options, $policy);
    }

    public function support(InputPolicyInterface|OutputPolicyInterface $policy): bool
    {
        $this->calls[] = [
            'method' => 'support',
            'policy' => $policy::class,
        ];

        return $this->policyHandler->support($policy);
    }

    public function reset(): void
    {
        $this->calls = [];
    }
}
