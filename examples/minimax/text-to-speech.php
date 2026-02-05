<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\MiniMax\PlatformFactory;
use Symfony\AI\Platform\Message\Content\Text;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('MINI_MAX_API_KEY'), http_client());

$result = $platform->invoke('speech-2.8-hd', new Text('Omg(sighs), the real danger is not that computers start thinking like people, but that people start thinking like computers. Computers can only help us with simple tasks.'));

echo $result->asBinary().\PHP_EOL;
