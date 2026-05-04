<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\HuggingFace;

use Symfony\AI\Platform\Bridge\HuggingFace\Output\TableQuestionAnsweringResult;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\RawResultInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class TableQuestionAnsweringClient extends AbstractTaskClient
{
    public const ENDPOINT = 'hf.'.Task::TABLE_QUESTION_ANSWERING;

    public function endpoint(): string
    {
        return self::ENDPOINT;
    }

    public function convert(RawResultInterface $raw, array $options = []): ObjectResult
    {
        return new ObjectResult(TableQuestionAnsweringResult::fromArray($raw->getData()));
    }
}
