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

use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\ExceptionInterface;
use Symfony\AI\Platform\Exception\InvalidRequestException;
use Symfony\AI\Platform\Exception\MissingModelSupportException;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Exception\RecoverableExceptionInterface;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Exception\TransportException;
use Symfony\AI\Platform\Exception\UnrecoverableExceptionInterface;
use Symfony\AI\Platform\Result\RawResultInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface ModelClientInterface
{
    public function supports(Model $model): bool;

    /**
     * @param array<string|int, mixed> $payload
     * @param array<string, mixed>     $options
     *
     * @throws ExceptionInterface
     * @throws RuntimeException
     * @throws RecoverableExceptionInterface
     * @throws UnrecoverableExceptionInterface
     * @throws AuthenticationException
     * @throws InvalidRequestException
     * @throws MissingModelSupportException
     * @throws RateLimitExceededException
     * @throws TransportException
     */
    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface;
}
