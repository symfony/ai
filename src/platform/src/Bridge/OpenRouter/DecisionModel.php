<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenRouter;

use Symfony\AI\Platform\Model;

/**
 * A decision model, served through the Decisions API of OpenRouter instead of the chat API.
 *
 * It accepts an {@see \Symfony\AI\Platform\Bridge\Decision\Evaluation} and answers with
 * {@see \Symfony\AI\Platform\Bridge\Decision\Answer\Answers}.
 *
 * @author Tim Lochmüller <tim@fruit-lab.de>
 */
final class DecisionModel extends Model
{
}
