<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Toolbox\Tool;

use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\Platform\Contract\JsonSchema\Attribute\With;

/**
 * @author Mathieu Ledru <matyo91@gmail.com>
 */
#[AsTool('human', 'Tool that asks user for input when the agent needs guidance')]
final readonly class HumanInput
{
    public function __construct(
        private mixed $promptFunc = null,
        private mixed $inputFunc = null,
    ) {
    }

    /**
     * Ask user for input when the agent needs guidance.
     *
     * @param string $query The question to ask the human
     */
    public function __invoke(
        #[With(maximum: 1000)]
        string $query,
    ): string {
        // Use custom prompt function or default
        $promptFunc = $this->promptFunc ?? fn (string $text) => $this->defaultPrompt($text);
        $inputFunc = $this->inputFunc ?? fn () => $this->defaultInput();

        // Display the prompt to the user
        $promptFunc($query);

        // Get input from the user
        $response = $inputFunc();

        return $response;
    }

    /**
     * Default prompt function that prints to console.
     */
    private function defaultPrompt(string $text): void
    {
        echo "\n".$text."\n";
    }

    /**
     * Default input function that reads from console.
     */
    private function defaultInput(): string
    {
        $handle = fopen('php://stdin', 'r');
        $input = trim(fgets($handle));
        fclose($handle);

        return $input;
    }
}
