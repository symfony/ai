<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\InputProcessor\SkillInputProcessor;
use Symfony\AI\Agent\Skill\GithubSkillLoader;
use Symfony\AI\Platform\Bridge\Anthropic\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('ANTHROPIC_API_KEY'), http_client());

// Load skills from a public GitHub repository
$loader = new GithubSkillLoader(
    [['repository' => 'owner/skills-repo']],
    http_client(),
);

$skillProcessor = new SkillInputProcessor($loader, ['my-skill'], true);

$agent = new Agent($platform, 'claude-sonnet-4-5-20250929', [$skillProcessor], []);

$result = $agent->call(new MessageBag(
    Message::forSystem('You are a helpful assistant.'),
    Message::ofUser('Help me with a task using the loaded skill.'),
));

echo $result->getContent().\PHP_EOL;
