<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Exception;

use Symfony\AI\Platform\Action;
use Symfony\AI\Platform\Model;

/**
 * @author Joshua Behrens <code@joshua-behrens.de>
 */
class InvalidActionArgumentException extends InvalidArgumentException
{
    /**
     * @param list<Action> $expectedActions
     */
    public function __construct(
        public readonly Model $model,
        public readonly Action $invalidAction,
        public readonly array $expectedActions,
        ?\Throwable $previous = null,
        int $code = 0,
    ) {
        $expectedAsString = implode(', ', array_map(static fn (Action $action): string => $action->name, $this->expectedActions));
        parent::__construct(
            'Tried invalid action ' . $this->invalidAction->name . ' on model ' . $this->model->getName() . ' where actions ' . $expectedAsString . ' where expected',
            $code,
            $previous,
        );
    }
}
