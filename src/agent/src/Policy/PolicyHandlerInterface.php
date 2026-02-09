<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Policy;

use Symfony\AI\Platform\Message\MessageBag;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
interface PolicyHandlerInterface
{
    /**
     * @param array<string, mixed> $options
     */
    public function handle(MessageBag $messages, array $options, InputPolicyInterface|OutputPolicyInterface $policy): void;

    public function support(InputPolicyInterface|OutputPolicyInterface $policy): bool;
}
