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
use Symfony\AI\Agent\Skill\ChainSkillLoader;
use Symfony\AI\Agent\Skill\FilesystemSkillLoader;
use Symfony\AI\Agent\Skill\GithubSkillLoader;
use Symfony\AI\Platform\Bridge\Anthropic\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('ANTHROPIC_API_KEY'), http_client());

// Combine local and remote skill loaders
// Local skills take precedence over remote ones
$localLoader = new FilesystemSkillLoader([__DIR__.'/.skills']);

$githubLoader = new GithubSkillLoader(
    [
        ['repository' => 'owner/public-skills'],
        ['repository' => 'owner/private-skills', 'token' => env('GITHUB_TOKEN'), 'branch' => 'main'],
    ],
    http_client(),
);

$chainLoader = new ChainSkillLoader([$localLoader, $githubLoader]);

$skillProcessor = new SkillInputProcessor($chainLoader, ['twig-component'], true);

$agent = new Agent($platform, 'claude-sonnet-4-5-20250929', [$skillProcessor], []);

$result = $agent->call(new MessageBag(
    Message::forSystem('You are a helpful assistant.'),
    Message::ofUser('Explain the usage of TwigComponents in a Symfony projet.'),
));

echo $result->getContent().\PHP_EOL;
