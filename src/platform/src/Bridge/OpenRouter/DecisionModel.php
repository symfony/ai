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
 * It answers the questions of the "questions" option with
 * {@see \Symfony\AI\Platform\Decision\Answer\Answers}.
 *
 * @author Tim Lochmüller <tim@fruit-lab.de>
 */
final class DecisionModel extends Model
{
}
