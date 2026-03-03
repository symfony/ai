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
use Symfony\AI\Agent\Skill\GithubSkillLoader;
use Symfony\AI\Agent\Toolbox\AgentProcessor;
use Symfony\AI\Agent\Toolbox\Tool\SkillTool;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Agent\Toolbox\ToolFactory\MemoryToolFactory;
use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('OPENAI_API_KEY'), http_client());

// Load skills from a private GitHub repository using a token
$loader = new GithubSkillLoader(
    [['repository' => 'owner/private-skills', 'token' => env('GITHUB_TOKEN')]],
    http_client(),
);

$tool = new SkillTool($loader, 'my-skill');

$toolFactory = new MemoryToolFactory();
$toolFactory->addTool($tool, 'skill_my_skill', 'Consult the my-skill skill for specialized knowledge. Optionally pass a reference path for detailed docs.');

$processor = new AgentProcessor(new Toolbox([$tool], $toolFactory));
$agent = new Agent($platform, 'gpt-5-mini', [$processor], [$processor]);

$result = $agent->call(new MessageBag(
    Message::forSystem('You are a helpful assistant.'),
    Message::ofUser('Help me with a task using the loaded skill.'),
));

echo $result->getContent().\PHP_EOL;
