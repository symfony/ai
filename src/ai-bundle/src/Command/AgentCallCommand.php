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

use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\AiBundle\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
#[AsCommand(
    name: 'ai:agent:call',
    aliases: ['ai:chat'],
    description: 'Call an agent',
)]
final class AgentCallCommand extends Command
{
    /**
     * @param ServiceLocator<AgentInterface> $agents
     */
    public function __construct(
        private readonly ServiceLocator $agents,
    ) {
        parent::__construct();
    }

    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestArgumentValuesFor('agent')) {
            $suggestions->suggestValues($this->getAvailableAgentNames());
        }
    }

    protected function configure(): void
    {
        $this
            ->addArgument('agent', InputArgument::REQUIRED, 'The name of the agent to chat with')
            ->setHelp(
                <<<'HELP'
                The <info>%command.name%</info> command allows you to chat with different agents.

                Usage:
                  <info>%command.full_name% [<agent_name>]</info>

                Examples:
                  <info>%command.full_name% wikipedia</info>

                If no agent is specified, you'll be prompted to select one interactively.

                The chat session is interactive. Type your messages and press Enter to send.
                Type 'exit' or 'quit' to end the conversation.
                HELP
            );
    }

    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        $agentArg = $input->getArgument('agent');

        if ($agentArg) {
            return;
        }

        $availableAgents = $this->getAvailableAgentNames();

        if (0 === \count($availableAgents)) {
            throw new InvalidArgumentException('No agents are configured.');
        }

        $question = new ChoiceQuestion(
            'Please select an agent to chat with:',
            $availableAgents,
            0
        );
        $question->setErrorMessage('Agent %s is invalid.');

        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');
        $selectedAgent = $helper->ask($input, $output, $question);

        $input->setArgument('agent', $selectedAgent);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $availableAgents = array_keys($this->agents->getProvidedServices());

        if (0 === \count($availableAgents)) {
            throw new InvalidArgumentException('No agents are configured.');
        }

        $agentArg = $input->getArgument('agent');
        $agentName = \is_string($agentArg) ? $agentArg : '';

        if ($agentName && !$this->agents->has($agentName)) {
            throw new InvalidArgumentException(\sprintf('Agent "%s" not found. Available agents: "%s"', $agentName, implode(', ', $availableAgents)));
        }

        if (!$agentName) {
            throw new InvalidArgumentException(\sprintf('Agent name is required. Available agents: "%s"', implode(', ', $availableAgents)));
        }

        $agent = $this->agents->get($agentName);

        $io = new SymfonyStyle($input, $output);

        $io->title(\sprintf('Chat with %s Agent', $agentName));
        $io->info('Type your message and press Enter. Type "exit" or "quit" to end the conversation.');
        $io->newLine();

        $messages = new MessageBag();
        $systemPromptDisplayed = false;

        while (true) {
            $userInput = $io->ask('You');

            if (!\is_string($userInput) || '' === trim($userInput)) {
                continue;
            }

            if (\in_array(strtolower($userInput), ['exit', 'quit'], true)) {
                $io->success('Goodbye!');
                break;
            }

            $messages->add(Message::ofUser($userInput));

            try {
                $result = $agent->call($messages);

                if (!$systemPromptDisplayed && null !== ($systemMessage = $messages->getSystemMessage())) {
                    $io->section('System Prompt');
                    $io->block($systemMessage->getContent(), null, 'fg=gray', ' ', true);
                    $systemPromptDisplayed = true;
                }

                if ($result instanceof TextResult) {
                    $io->write('<fg=yellow>Assistant</>:');
                    $io->writeln('');
                    $io->writeln($result->getContent());
                    $io->newLine();

                    $messages->add(Message::ofAssistant($result->getContent()));
                } else {
                    $io->error('Unexpected response type from agent');
                }
            } catch (\Exception $e) {
                $io->error(\sprintf('Error: %s', $e->getMessage()));

                if ($output->isVerbose()) {
                    $io->writeln('');
                    $io->writeln('<comment>Exception trace:</comment>');
                    $io->text($e->getTraceAsString());
                }
            }
        }

        return Command::SUCCESS;
    }

    /**
     * @return string[]
     */
    private function getAvailableAgentNames(): array
    {
        return array_keys($this->agents->getProvidedServices());
    }
}
