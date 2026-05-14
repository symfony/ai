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

use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\TextResult;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class AutomaticSpeechRecognitionClient extends AbstractTaskClient
{
    public const ENDPOINT = 'hf.'.Task::AUTOMATIC_SPEECH_RECOGNITION;

    public function endpoint(): string
    {
        return self::ENDPOINT;
    }

    public function convert(RawResultInterface $raw, array $options = []): TextResult
    {
        $data = $raw->getData();

        return new TextResult($data['text'] ?? '');
    }
}
