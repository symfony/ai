<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tui\Command;

use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\Tool\Tool;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Tui\Event\SelectionChangeEvent;
use Symfony\Component\Tui\Style\Border;
use Symfony\Component\Tui\Style\Direction;
use Symfony\Component\Tui\Style\Padding;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Style\StyleSheet;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\MarkdownWidget;
use Symfony\Component\Tui\Widget\SelectListWidget;
use Symfony\Component\Tui\Widget\TextWidget;

/**
 * Browse the registered tools in a rich terminal UI: the list shows every
 * `ai.tool` (the model-facing functions), and the detail pane shows each tool's
 * description, what it calls, and its input JSON Schema.
 *
 * The model-facing sibling of `app:capabilities`: tools are the subset of
 * capabilities projected for an agent/MCP to call, and unlike a capability they
 * carry their own hand-written schema.
 */
#[AsCommand(
    name: 'app:tools',
    description: 'Browse the registered tools and their schemas (Symfony TUI component).',
)]
final class ToolsTuiCommand extends Command
{
    private readonly Toolbox $toolbox;

    /**
     * @param iterable<object> $tools
     */
    public function __construct(
        #[AutowireIterator('ai.tool')]
        iterable $tools,
    ) {
        parent::__construct();

        $this->toolbox = new Toolbox($tools);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $tools = $this->toolbox->getTools();
        if ([] === $tools) {
            $io->error('No tools are registered (ai.tool). Configure an agent with tools, or tag an #[AsTool] service.');

            return Command::FAILURE;
        }

        $entries = [];
        $items = [];
        $index = 0;
        foreach ($tools as $tool) {
            $value = (string) $index++;
            $entries[$value] = $tool;
            $items[] = ['value' => $value, 'label' => $tool->getName(), 'description' => $this->oneLine($tool->getDescription())];
        }

        $tui = new Tui($this->buildStyleSheet());

        $listPane = new ContainerWidget();
        $listPane->addStyleClass('list');
        $listPane->add(new TextWidget(\sprintf('%d tools', \count($items))));
        $list = new SelectListWidget($items, min(\count($items), 20));
        $listPane->add($list);

        $detail = new MarkdownWidget($this->detailMarkdown($entries[$items[0]['value']]));
        $detailPane = new ContainerWidget();
        $detailPane->addStyleClass('detail');
        $detailPane->expandVertically(true);
        $detailPane->add($detail);

        $row = new ContainerWidget();
        $row->setStyle(new Style(direction: Direction::Horizontal, gap: 1));
        $row->expandVertically(true);
        $row->add($listPane)->add($detailPane);

        $hint = new TextWidget('↑/↓: browse   ·   Enter/Esc: quit');
        $hint->addStyleClass('hint');

        $tui->add($row)->add($hint);
        $tui->setFocus($list);

        $list->onSelectionChange(function (SelectionChangeEvent $event) use ($tui, $detail, $entries): void {
            $detail->setText($this->detailMarkdown($entries[$event->getValue()]));
            $tui->requestRender();
            $tui->processRender();
        });
        $list->onSelect(static fn () => $tui->stop());
        $list->onCancel(static fn () => $tui->stop());

        $tui->run();

        return Command::SUCCESS;
    }

    private function detailMarkdown(Tool $tool): string
    {
        $reference = $tool->getReference();

        $md = \sprintf("## %s\n\n", $tool->getName());
        $md .= \sprintf("%s\n\n", $tool->getDescription());
        $md .= \sprintf("**Calls:** `%s::%s`\n\n", $reference->getClass(), $reference->getMethod());
        $md .= "**Input schema:**\n\n```json\n".(string) json_encode($tool->getParameters() ?? new \stdClass(), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES)."\n```\n";

        return $md;
    }

    private function oneLine(string $text): string
    {
        $text = preg_replace('/\s+/', ' ', trim($text)) ?? $text;

        return mb_strlen($text) > 60 ? mb_substr($text, 0, 59).'…' : $text;
    }

    private function buildStyleSheet(): StyleSheet
    {
        $styles = new StyleSheet();
        $styles->addRule('.list', new Style(padding: Padding::all(1), border: Border::all(1, 'rounded', 'cyan')));
        $styles->addRule('.detail', new Style(padding: Padding::all(1), border: Border::all(1, 'rounded', 'gray')));
        $styles->addRule('.hint', new Style(color: 'gray', dim: true));

        return $styles;
    }
}
