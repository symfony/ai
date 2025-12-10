<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Speech;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
trait SpeechBagAwareTrait
{
    private ?SpeechBag $speechBag = null;

    public function addSpeech(?Speech $speech): void
    {
        if (null === $this->speechBag) {
            $this->speechBag = new SpeechBag();
        }

        $this->speechBag->add($speech);
    }

    public function getSpeech(string $identifier): Speech
    {
        if (null === $this->speechBag) {
            $this->speechBag = new SpeechBag();
        }

        return $this->speechBag->get($identifier);
    }

    public function getSpeechBag(): SpeechBag
    {
        return $this->speechBag ??= new SpeechBag();
    }
}
