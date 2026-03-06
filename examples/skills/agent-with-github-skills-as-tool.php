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

$loader = new GithubSkillLoader(
    [['repository' => 'smnandre/symfony-ux-skills']],
    http_client(),
);

$tool = new SkillTool($loader, 'twig-component');

$toolFactory = new MemoryToolFactory();
$toolFactory->addTool($tool, 'skill_twig_component', 'Consult the my-skill skill for specialized knowledge. Optionally pass a reference path for detailed docs.', 'loadSkill');

$processor = new AgentProcessor(new Toolbox([$tool], $toolFactory));
$agent = new Agent($platform, 'gpt-5-mini', [$processor], [$processor]);

$result = $agent->call(new MessageBag(
    Message::forSystem('You are a helpful assistant.'),
    Message::ofUser('Explain to me the concept behind Twig components and their advantages.'),
));

echo $result->getContent().\PHP_EOL;
