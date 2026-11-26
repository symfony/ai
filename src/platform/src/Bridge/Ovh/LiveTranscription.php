<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Ovh;

use Symfony\AI\Platform\Model;

/**
 * A Gladia model, which transcribes audio streamed over a WebSocket session opened through the live API of OVHcloud AI Endpoints.
 *
 * @author Loïc Sapone <loic@sapone.fr>
 */
final class LiveTranscription extends Model
{
}
