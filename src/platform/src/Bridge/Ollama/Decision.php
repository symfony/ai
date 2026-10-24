<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Ollama;

/**
 * A decision (classification) model, served by Ollama through "POST /v1/systemone" instead of the chat API.
 *
 * It has no capabilities on purpose: it only accepts an
 * {@see \Symfony\AI\Platform\Bridge\Decision\Evaluation} and answers with
 * {@see \Symfony\AI\Platform\Bridge\Decision\Answer\Answers}.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class Decision extends Ollama
{
}
