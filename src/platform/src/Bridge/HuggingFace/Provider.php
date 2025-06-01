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

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface Provider
{
    public const CEREBRAS = 'cerebras';
    public const COHERE = 'cohere';
    public const FAL_AI = 'fal-ai';
    public const FIREWORKS = 'fireworks-ai';
    public const HYPERBOLIC = 'hyperbolic';
    public const HF_INFERENCE = 'hf-inference';
    public const NEBIUS = 'nebius';
    public const NOVITA = 'novita';
    public const REPLICATE = 'replicate';
    public const SAMBA_NOVA = 'sambanova';
    public const TOGETHER = 'together';
}
