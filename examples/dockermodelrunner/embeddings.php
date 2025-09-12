<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\DockerModelRunner\Embeddings;
use Symfony\AI\Platform\Bridge\DockerModelRunner\PlatformFactory;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('DOCKER_MODEL_RUNNER_HOST_URL'), http_client());
$response = $platform->invoke(new Embeddings(Embeddings::NOMIC_EMBED_TEXT), <<<TEXT
    Once upon a time, there was a country called Japan. It was a beautiful country with a lot of mountains and rivers.
    The people of Japan were very kind and hardworking. They loved their country very much and took care of it. The
    country was very peaceful and prosperous. The people lived happily ever after.
    TEXT);

print_vectors($response);
