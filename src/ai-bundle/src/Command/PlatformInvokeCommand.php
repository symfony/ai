<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AiBundle\Command;

use Symfony\AI\AiBundle\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * @author Oskar Stark <oskarstark@gmail.com>
 */
#[AsCommand(
    name: 'ai:platform:invoke',
    description: 'Invoke an AI platform with a message',
)]
final class PlatformInvokeCommand extends Command
{
    private string $message;
    private PlatformInterface $platform;
    private Model $model;

    /**
     * @param ServiceLocator<PlatformInterface> $platforms
     */
    public function __construct(
        private readonly ServiceLocator $platforms,
    ) {
        parent::__construct();
    }

    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestArgumentValuesFor('platform')) {
            $suggestions->suggestValues($this->getAvailablePlatformNames());
        }
    }

    protected function configure(): void
    {
        $this
            ->addArgument('platform', InputArgument::REQUIRED, 'The name of the configured platform to invoke')
            ->addArgument('model', InputArgument::REQUIRED, 'The model to use for the request')
            ->addArgument('message', InputArgument::REQUIRED, 'The message to send to the AI platform')
            ->setHelp(
                <<<'HELP'
                The <info>%command.name%</info> command allows you to invoke configured AI platforms with a message.

                Usage:
                  <info>%command.full_name% <platform_name> <model> "<message>"</info>

                Examples:
                  <info>%command.full_name% openai gpt-4o-mini "Hello, world!"</info>
                  <info>%command.full_name% anthropic claude-3-5-sonnet-20241022 "Explain quantum physics"</info>

                Available platforms depend on your configuration in config/packages/ai.yaml
                HELP
            );
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $availablePlatforms = $this->getAvailablePlatformNames();

        if (0 === \count($availablePlatforms)) {
            throw new InvalidArgumentException('No platforms are configured.');
        }

        $platformName = trim((string) $input->getArgument('platform'));

        if ('' === $platformName) {
            throw new InvalidArgumentException('Platform name is required.');
        }

        $platformServiceId = $this->getPlatformServiceId($platformName);

        if (null === $platformServiceId || !$this->platforms->has($platformServiceId)) {
            throw new InvalidArgumentException(\sprintf('Platform "%s" not found. Available platforms: "%s"', $platformName, implode(', ', $availablePlatforms)));
        }

        $this->platform = $this->platforms->get($platformServiceId);

        $modelName = trim((string) $input->getArgument('model'));

        if ('' === $modelName) {
            throw new InvalidArgumentException('Model is required.');
        }

        $this->model = new Model($modelName);

        $this->message = trim((string) $input->getArgument('message'));

        if ('' === $this->message) {
            throw new InvalidArgumentException('Message is required.');
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $messages = new MessageBag();
            $messages->add(Message::ofUser($this->message));
            
            $resultPromise = $this->platform->invoke($this->model, $messages);
            $result = $resultPromise->getResult();

            if ($result instanceof TextResult) {
                $platformName = trim((string) $input->getArgument('platform'));
                $io->success(\sprintf('Response from %s:', $platformName));
                $io->writeln($result->getContent());
            } else {
                $io->error('Unexpected response type from platform');
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $io->error(\sprintf('Error: %s', $e->getMessage()));

            if ($output->isVerbose()) {
                $io->writeln('');
                $io->writeln('<comment>Exception trace:</comment>');
                $io->text($e->getTraceAsString());
            }

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * @return string[]
     */
    private function getAvailablePlatformNames(): array
    {
        $platformNames = [];
        
        foreach (array_keys($this->platforms->getProvidedServices()) as $serviceId) {
            if (str_starts_with($serviceId, 'ai.platform.')) {
                $platformNames[] = substr($serviceId, 12); // Remove 'ai.platform.' prefix
            }
        }

        return $platformNames;
    }

    private function getPlatformServiceId(string $platformName): ?string
    {
        $serviceId = 'ai.platform.' . $platformName;
        
        return $this->platforms->has($serviceId) ? $serviceId : null;
    }

}