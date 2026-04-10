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
use Symfony\AI\Platform\Exception\ContentFilterException;
use Symfony\AI\Platform\Exception\ExceedContextSizeException;
use Symfony\AI\Platform\Exception\ExceptionInterface;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\InvalidRequestException;
use Symfony\AI\Platform\Exception\MissingModelSupportException;
use Symfony\AI\Platform\Exception\ModelNotFoundException;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Exception\RecoverableExceptionInterface;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Exception\TransportException;
use Symfony\AI\Platform\Exception\UnexpectedResultTypeException;
use Symfony\AI\Platform\Exception\UnrecoverableExceptionInterface;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\Result\DeferredResult;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface PlatformInterface
{
    /**
     * @param non-empty-string           $model   The model name
     * @param array<mixed>|string|object $input   The input data
     * @param array<string, mixed>       $options The options to customize the model invocation
     *
     * @throws ExceptionInterface
     * @throws RuntimeException
     * @throws RecoverableExceptionInterface
     * @throws UnrecoverableExceptionInterface
     * @throws AuthenticationException
     * @throws InvalidArgumentException
     * @throws InvalidRequestException
     * @throws MissingModelSupportException
     * @throws ModelNotFoundException
     * @throws RateLimitExceededException
     * @throws TransportException
     * @throws ContentFilterException
     * @throws ExceedContextSizeException
     * @throws UnexpectedResultTypeException
     */
    public function invoke(string $model, array|string|object $input, array $options = []): DeferredResult;

    public function getModelCatalog(): ModelCatalogInterface;
}
