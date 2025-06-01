<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent;

use Symfony\AI\Platform\Message\MessageBagInterface;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Response\ResponseInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Output
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        public readonly Model $model,
        public ResponseInterface $response,
        public readonly MessageBagInterface $messages,
        public readonly array $options,
    ) {
    }
}
