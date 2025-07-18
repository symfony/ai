<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Bedrock;

use AsyncAws\BedrockRuntime\Result\InvokeModelResponse;
use Symfony\AI\Platform\Result\RawResultInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final readonly class RawBedrockResult implements RawResultInterface
{
    public function __construct(
        private InvokeModelResponse $invokeModelResponse,
    ) {
    }

    public function getData(): array
    {
        return json_decode($this->invokeModelResponse->getBody(), true, 512, \JSON_THROW_ON_ERROR);
    }

    public function getObject(): InvokeModelResponse
    {
        return $this->invokeModelResponse;
    }
}
