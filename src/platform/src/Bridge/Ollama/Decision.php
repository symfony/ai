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
 * Only the decision and the image input capabilities apply, the latter when the model supports vision. It answers
 * the questions of the "questions" option with
 * {@see \Symfony\AI\Platform\Decision\Answer\Answers}.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class Decision extends Ollama
{
}
