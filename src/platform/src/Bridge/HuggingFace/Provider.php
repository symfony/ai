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
 * Based on the list of supported providers at
 * https://huggingface.co/docs/inference-providers/index.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface Provider
{
    public const CEREBRAS = 'cerebras';
    public const COHERE = 'cohere';
    public const FAL_AI = 'fal-ai';
    public const FEATHERLESS_AI = 'featherless-ai';
    public const FIREWORKS = 'fireworks-ai';
    public const GROQ = 'groq';
    public const HF_INFERENCE = 'hf-inference';
    public const HYPERBOLIC = 'hyperbolic';
    public const NEBIUS = 'nebius';
    public const NOVITA = 'novita';
    public const NSCALE = 'nscale';
    public const PUBLIC_AI = 'publicai';
    public const REPLICATE = 'replicate';
    public const SAMBA_NOVA = 'sambanova';
    public const SCALEWAY = 'scaleway';
    public const TOGETHER = 'together';
    public const WAVE_SPEED_AI = 'wavespeed';
    public const Z_AI = 'zai-org';
}
