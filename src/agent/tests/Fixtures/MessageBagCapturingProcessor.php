<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Fixtures;

use Symfony\AI\Agent\Input;
use Symfony\AI\Agent\InputProcessorInterface;
use Symfony\AI\Platform\Message\MessageBag;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class MessageBagCapturingProcessor implements InputProcessorInterface
{
    public ?MessageBag $messageBag = null;

    public function processInput(Input $input): void
    {
        $this->messageBag = $input->getMessageBag();
    }
}
