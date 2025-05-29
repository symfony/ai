<?php

declare(strict_types=1);

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App;

use Symfony\AI\McpSdk\Capability\Prompt\MetadataInterface;
use Symfony\AI\McpSdk\Capability\Prompt\PromptGet;
use Symfony\AI\McpSdk\Capability\Prompt\PromptGetResult;
use Symfony\AI\McpSdk\Capability\Prompt\PromptGetResultMessages;
use Symfony\AI\McpSdk\Capability\Prompt\PromptGetterInterface;

class ExamplePrompt implements MetadataInterface, PromptGetterInterface
{
    public function get(PromptGet $input): PromptGetResult
    {
        $firstName = $input->arguments['first name'] ?? null;

        return new PromptGetResult(
            $this->getDescription(),
            [new PromptGetResultMessages(
                'user',
                \sprintf('Hello %s', $firstName ?? 'World')
            )]
        );
    }

    public function getName(): string
    {
        return 'Greet';
    }

    public function getDescription(): ?string
    {
        return 'Greet a person with a nice message';
    }

    public function getArguments(): array
    {
        return [
            [
                'name' => 'first name',
                'description' => 'The name of the person to greet',
                'required' => false,
            ],
        ];
    }
}
