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

use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;

/**
 * Returns the raw image bytes — the upstream content type is preserved on the
 * {@see BinaryResult} so callers know whether they got PNG, JPEG, etc.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class TextToImageClient extends AbstractTaskClient
{
    public const ENDPOINT = 'hf.'.Task::TEXT_TO_IMAGE;

    public function endpoint(): string
    {
        return self::ENDPOINT;
    }

    public function convert(RawResultInterface $raw, array $options = []): BinaryResult
    {
        $contentType = null;
        if ($raw instanceof RawHttpResult) {
            $contentType = $raw->getObject()->getHeaders(false)['content-type'][0] ?? null;
        }

        $bytes = $raw instanceof RawHttpResult
            ? $raw->getObject()->getContent(false)
            : (string) ($raw->getData()[0] ?? '');

        return new BinaryResult($bytes, $contentType);
    }
}
