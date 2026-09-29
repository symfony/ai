<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Execution\Update;

use Symfony\AI\Agent\Execution\UpdateInterface;
use Symfony\AI\Agent\Execution\UpdateType;
use Symfony\AI\Platform\Result\ResultInterface;

/**
 * Carries a single round's own result, yielded right after the model responds and before the loop
 * decides whether to call another tool.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ModelResult implements UpdateInterface
{
    public function __construct(
        private readonly ResultInterface $result,
    ) {
    }

    public function getType(): UpdateType
    {
        return UpdateType::ModelResult;
    }

    public function getResult(): ResultInterface
    {
        return $this->result;
    }
}
