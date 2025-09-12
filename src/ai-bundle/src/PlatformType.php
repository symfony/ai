<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AiBundle;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
enum PlatformType: string
{
    case Anthropic = 'anthropic';
    case Azure = 'azure';
    case Cerebras = 'cerebras';
    case ElevenLabs = 'eleven_labs';
    case Gemini = 'gemini';
    case LmStudio = 'lmstudio';
    case Mistral = 'mistral';
    case Ollama = 'ollama';
    case OpenAi = 'openai';
    case OpenRouter = 'openrouter';
    case Perplexity = 'perplexity';
    case VertexAi = 'vertexai';
    case Voyage = 'voyage';
}
