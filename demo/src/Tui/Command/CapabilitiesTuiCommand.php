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

use Symfony\AI\Platform\Bridge\ModelsDev\DataLoader;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Contract\JsonSchema\Factory;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\Tui\Event\InputEvent;
use Symfony\Component\Tui\Event\SelectEvent;
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
use Symfony\Contracts\Service\ServiceProviderInterface;

/**
 * Explore the platform capability surface in a rich terminal UI, two ways:
 *
 *  - by platform: pick a platform, then browse/search its models;
 *  - by capability: pick a capability ("I want to OCR"), then see every model on
 *    every platform that can do it — the capability-first view.
 *
 * The detail pane derives input/output shape and the serialized input-DTO schema
 * from the model's capabilities, and shows pricing from models.dev when known.
 * This is the layer your workflows call into with `$platform->invoke()`; OCR
 * shows up here (a model with `input-pdf`), never in `app:tools`.
 */
#[AsCommand(
    name: 'app:capabilities',
    description: 'Explore platform capabilities, models, input shapes and pricing (Symfony TUI component).',
)]
final class CapabilitiesTuiCommand extends Command
{
    private const BROWSE_BY_CAPABILITY = "\x00capability";

    private readonly Factory $schemaFactory;

    /**
     * @var array<string, array{cost: array<string, float>|null, limit: array<string, int>|null}>|null
     */
    private ?array $pricing = null;

    /**
     * @param ServiceProviderInterface<PlatformInterface> $platforms
     */
    public function __construct(
        #[AutowireLocator('ai.platform', indexAttribute: 'name')]
        private readonly ServiceProviderInterface $platforms,
    ) {
        parent::__construct();

        $this->schemaFactory = new Factory();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $platformNames = array_keys($this->platforms->getProvidedServices());
        if ([] === $platformNames) {
            $io->error('No platforms are configured (ai.platform.*).');

            return Command::FAILURE;
        }

        $io->writeln('<info>Loading models.dev pricing…</info>');
        $this->loadPricing();

        while (true) {
            $choice = $this->selectEntry($platformNames);
            if (null === $choice) {
                return Command::SUCCESS;
            }

            if (self::BROWSE_BY_CAPABILITY === $choice) {
                $capability = $this->selectCapability();
                if (null !== $capability) {
                    $this->browse($this->modelsForCapability($capability), \sprintf('Models that can: %s — %s', $capability->value, $capability->description()));
                }

                continue;
            }

            $this->browse($this->modelsForPlatform($choice), \sprintf('%s · models', $choice));
        }
    }

    /**
     * @param list<string> $platformNames
     */
    private function selectEntry(array $platformNames): ?string
    {
        $tui = new Tui($this->buildStyleSheet());
        $selected = null;

        $items = [['value' => self::BROWSE_BY_CAPABILITY, 'label' => '▸ Browse by capability', 'description' => 'what do you want to do?']];
        foreach ($platformNames as $name) {
            $count = \count($this->platforms->get($name)->getModelCatalog()->getModels());
            $items[] = ['value' => $name, 'label' => $name, 'description' => \sprintf('%d models', $count)];
        }

        $pane = new ContainerWidget();
        $pane->addStyleClass('list');
        $pane->add(new TextWidget('Browse capabilities by:'));
        $list = new SelectListWidget($items, \count($items));
        $pane->add($list);

        $hint = new TextWidget('↑/↓: browse   ·   Enter: open   ·   Esc: quit');
        $hint->addStyleClass('hint');

        $tui->add($pane)->add($hint);
        $tui->setFocus($list);

        $list->onSelect(static function (SelectEvent $event) use ($tui, &$selected): void {
            $selected = $event->getValue();
            $tui->stop();
        });
        $list->onCancel(static fn () => $tui->stop());

        $tui->run();

        return $selected;
    }

    private function selectCapability(): ?Capability
    {
        // Only offer capabilities at least one configured model actually declares.
        $present = [];
        foreach (array_keys($this->platforms->getProvidedServices()) as $name) {
            foreach ($this->platforms->get($name)->getModelCatalog()->getModels() as $info) {
                foreach ($info['capabilities'] as $capability) {
                    $present[$capability->value] = $capability;
                }
            }
        }
        ksort($present);

        $tui = new Tui($this->buildStyleSheet());
        $selected = null;

        $items = [];
        foreach ($present as $capability) {
            $items[] = ['value' => $capability->value, 'label' => $capability->value, 'description' => $capability->description()];
        }

        $pane = new ContainerWidget();
        $pane->addStyleClass('list');
        $pane->add(new TextWidget('What do you want a model to do?'));
        $list = new SelectListWidget($items, min(\count($items), 22));
        $pane->add($list);

        $hint = new TextWidget('↑/↓: browse   ·   Enter: find models   ·   Esc: back');
        $hint->addStyleClass('hint');

        $tui->add($pane)->add($hint);
        $tui->setFocus($list);

        $list->onSelect(static function (SelectEvent $event) use ($tui, &$selected): void {
            $selected = $event->getValue();
            $tui->stop();
        });
        $list->onCancel(static fn () => $tui->stop());

        $tui->run();

        return null === $selected ? null : Capability::from($selected);
    }

    /**
     * @return list<array{platform: string, model: string, info: array{class: string, capabilities: list<Capability>}}>
     */
    private function modelsForPlatform(string $platformName): array
    {
        $models = $this->platforms->get($platformName)->getModelCatalog()->getModels();
        ksort($models);

        $rows = [];
        foreach ($models as $modelName => $info) {
            $rows[] = ['platform' => $platformName, 'model' => $modelName, 'info' => $info];
        }

        return $rows;
    }

    /**
     * @return list<array{platform: string, model: string, info: array{class: string, capabilities: list<Capability>}}>
     */
    private function modelsForCapability(Capability $capability): array
    {
        $rows = [];
        foreach (array_keys($this->platforms->getProvidedServices()) as $platformName) {
            $models = $this->platforms->get($platformName)->getModelCatalog()->getModels();
            ksort($models);
            foreach ($models as $modelName => $info) {
                if (\in_array($capability, $info['capabilities'], true)) {
                    $rows[] = ['platform' => $platformName, 'model' => $modelName, 'info' => $info];
                }
            }
        }

        return $rows;
    }

    /**
     * @param list<array{platform: string, model: string, info: array{class: string, capabilities: list<Capability>}}> $rows
     */
    private function browse(array $rows, string $title): void
    {
        if ([] === $rows) {
            return;
        }

        $entries = [];
        $items = [];
        $index = 0;
        foreach ($rows as $row) {
            $value = (string) $index++;
            $entries[$value] = $row;
            $items[] = ['value' => $value, 'label' => $row['model'], 'description' => $row['platform'].' · '.$this->capabilitySummary($row['info']['capabilities'])];
        }

        $tui = new Tui($this->buildStyleSheet());

        $searchLine = new TextWidget('Search: (type to filter by name or capability)');
        $listPane = new ContainerWidget();
        $listPane->addStyleClass('list');
        $listPane->add(new TextWidget(\sprintf('%s · %d models', $title, \count($items))));
        $listPane->add($searchLine);
        $list = new SelectListWidget($items, min(\count($items), 20));
        $listPane->add($list);

        $detail = new MarkdownWidget($this->detailMarkdown($entries[$items[0]['value']]));
        $detailPane = new ContainerWidget();
        $detailPane->addStyleClass('detail');
        $detailPane->expandVertically(true);
        $detailPane->add($detail);

        $rowWidget = new ContainerWidget();
        $rowWidget->setStyle(new Style(direction: Direction::Horizontal, gap: 1));
        $rowWidget->expandVertically(true);
        $rowWidget->add($listPane)->add($detailPane);

        $hint = new TextWidget('type: filter   ·   ↑/↓: browse   ·   Enter/Esc: back');
        $hint->addStyleClass('hint');

        $tui->add($rowWidget)->add($hint);
        $tui->setFocus($list);

        $search = '';
        $applyFilter = function () use ($tui, $list, $items, &$search, $searchLine, $detail, $entries): void {
            $query = strtolower(trim($search));
            $filtered = '' === $query
                ? $items
                : array_values(array_filter($items, static fn (array $item): bool => str_contains(strtolower($item['label']), $query) || str_contains(strtolower($item['description']), $query)));

            $list->setItems($filtered);
            $searchLine->setText('' === $search
                ? 'Search: (type to filter by name or capability)'
                : \sprintf('Search: %s   (%d match%s)', $search, \count($filtered), 1 === \count($filtered) ? '' : 'es'));
            $detail->setText([] !== $filtered ? $this->detailMarkdown($entries[$filtered[0]['value']]) : '_no match_');
            $tui->requestRender();
            $tui->processRender();
        };

        $tui->addListener(static function (InputEvent $event) use (&$search, $applyFilter): void {
            $data = $event->getData();
            if ("\x7f" === $data || "\x08" === $data) {
                if ('' !== $search) {
                    $search = mb_substr($search, 0, -1);
                    $applyFilter();
                }

                return;
            }
            if (1 === \strlen($data) && \ord($data) >= 32 && \ord($data) < 127) {
                $search .= $data;
                $applyFilter();
            }
        });

        $list->onSelectionChange(function (SelectionChangeEvent $event) use ($tui, $detail, $entries): void {
            $detail->setText($this->detailMarkdown($entries[$event->getValue()]));
            $tui->requestRender();
            $tui->processRender();
        });
        $list->onSelect(static fn () => $tui->stop());
        $list->onCancel(static fn () => $tui->stop());

        $tui->run();
    }

    /**
     * @param array{platform: string, model: string, info: array{class: string, capabilities: list<Capability>}} $entry
     */
    private function detailMarkdown(array $entry): string
    {
        $info = $entry['info'];
        $capabilities = $info['capabilities'];
        $values = array_map(static fn (Capability $capability): string => $capability->value, $capabilities);

        $md = \sprintf("## %s\n\n", $entry['model']);
        $md .= \sprintf("**Platform:** `%s`\n\n", $entry['platform']);
        $md .= \sprintf("**Class:** `%s`\n\n", $info['class']);

        if (null !== ($price = $this->pricingLine($entry['model']))) {
            $md .= \sprintf("**Pricing (models.dev):** %s\n\n", $price);
        }

        $md .= \sprintf("**Capabilities:** %s\n\n", implode(', ', $values));
        $md .= \sprintf("**Input shape:** %s\n\n", $this->inputShape($values));
        $md .= \sprintf("**Output shape:** %s\n\n", $this->outputShape($values));
        $md .= "**Invoke:**\n\n```php\n".$this->sampleCall($entry['model'], $values)."\n```\n\n";

        $dto = $this->inputDtoClass($capabilities);
        if (null !== $dto) {
            $md .= \sprintf("**Input schema** (from `%s`):\n\n```json\n%s\n```\n", $dto, json_encode($this->schemaFactory->buildProperties($dto), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));
        }

        return $md;
    }

    /**
     * The single-modality input DTO, sourced from the capability itself
     * (Capability::inputContentType()) rather than a hand-rolled map.
     *
     * @param list<Capability> $capabilities
     *
     * @return class-string|null
     */
    private function inputDtoClass(array $capabilities): ?string
    {
        if (\in_array(Capability::INPUT_MESSAGES, $capabilities, true)) {
            return null;
        }

        foreach ([Capability::INPUT_PDF, Capability::INPUT_IMAGE, Capability::INPUT_AUDIO, Capability::INPUT_VIDEO] as $capability) {
            if (\in_array($capability, $capabilities, true)) {
                return $capability->inputContentType();
            }
        }

        return null;
    }

    /**
     * @param string[] $values
     */
    private function inputShape(array $values): string
    {
        $content = [];
        foreach ([
            Capability::INPUT_PDF->value => 'Document|DocumentUrl',
            Capability::INPUT_IMAGE->value => 'Image|ImageUrl',
            Capability::INPUT_AUDIO->value => 'Audio',
            Capability::INPUT_VIDEO->value => 'Video',
            Capability::INPUT_TEXT->value => 'Text',
        ] as $capability => $type) {
            if (\in_array($capability, $values, true)) {
                $content[] = $type;
            }
        }

        if (\in_array(Capability::INPUT_MESSAGES->value, $values, true)) {
            return [] === $content ? 'MessageBag' : \sprintf('MessageBag (may contain %s)', implode(', ', $content));
        }

        if (\in_array(Capability::EMBEDDINGS->value, $values, true) || \in_array(Capability::INPUT_MULTIPLE->value, $values, true)) {
            return 'string | string[]';
        }

        return [] === $content ? 'string' : implode(' | ', $content);
    }

    /**
     * @param string[] $values
     */
    private function outputShape(array $values): string
    {
        $shapes = [];
        if (\in_array(Capability::EMBEDDINGS->value, $values, true)) {
            $shapes[] = 'VectorResult';
        }
        if (\in_array(Capability::OUTPUT_STRUCTURED->value, $values, true)) {
            $shapes[] = 'ObjectResult';
        }
        if (\in_array(Capability::OUTPUT_TEXT->value, $values, true)) {
            $shapes[] = 'TextResult';
        }
        if (\in_array(Capability::OUTPUT_IMAGE->value, $values, true) || \in_array(Capability::TEXT_TO_IMAGE->value, $values, true)) {
            $shapes[] = 'BinaryResult (image)';
        }
        if (\in_array(Capability::OUTPUT_AUDIO->value, $values, true) || \in_array(Capability::TEXT_TO_SPEECH->value, $values, true)) {
            $shapes[] = 'BinaryResult (audio)';
        }
        if (\in_array(Capability::RERANKING->value, $values, true)) {
            $shapes[] = 'RerankingResult';
        }

        return [] === $shapes ? '(unknown)' : implode(' | ', $shapes);
    }

    /**
     * @param string[] $values
     */
    private function sampleCall(string $model, array $values): string
    {
        $hasMessages = \in_array(Capability::INPUT_MESSAGES->value, $values, true);

        $payload = match (true) {
            !$hasMessages && \in_array(Capability::INPUT_PDF->value, $values, true) => 'new DocumentUrl($url)',
            !$hasMessages && \in_array(Capability::INPUT_IMAGE->value, $values, true) => 'new ImageUrl($url)',
            !$hasMessages && \in_array(Capability::INPUT_AUDIO->value, $values, true) => 'Audio::fromFile($path)',
            \in_array(Capability::EMBEDDINGS->value, $values, true) => '$text',
            default => 'new MessageBag(Message::ofUser($prompt))',
        };

        return \sprintf("\$platform->invoke('%s', %s)", $model, $payload);
    }

    /**
     * @param list<Capability> $capabilities
     */
    private function capabilitySummary(array $capabilities): string
    {
        $summary = implode(', ', array_map(static fn (Capability $capability): string => $capability->value, $capabilities));

        return mb_strlen($summary) > 40 ? mb_substr($summary, 0, 39).'…' : $summary;
    }

    /**
     * One-line pricing/limit summary from models.dev for a model, or null.
     */
    private function pricingLine(string $model): ?string
    {
        $entry = $this->loadPricing()[$model] ?? null;
        if (null === $entry) {
            return null;
        }

        $parts = [];
        $cost = $entry['cost'] ?? [];
        if (isset($cost['input'])) {
            $parts[] = \sprintf('$%s in', rtrim(rtrim(\sprintf('%.3f', $cost['input']), '0'), '.'));
        }
        if (isset($cost['output'])) {
            $parts[] = \sprintf('$%s out', rtrim(rtrim(\sprintf('%.3f', $cost['output']), '0'), '.'));
        }
        if ([] !== $parts) {
            $parts = [implode(' / ', $parts).' per 1M tokens'];
        }
        $limit = $entry['limit'] ?? [];
        if (isset($limit['context'])) {
            $parts[] = \sprintf('%s ctx', number_format($limit['context']));
        }

        return [] === $parts ? null : implode(' · ', $parts);
    }

    /**
     * Lazily loads models.dev pricing/limits, keyed by model id, from the
     * `symfony/models-dev` package's bundled data file. If the package is not
     * installed, pricing simply degrades to nothing.
     *
     * @return array<string, array{cost: array<string, float>|null, limit: array<string, int>|null}>
     */
    private function loadPricing(): array
    {
        if (null !== $this->pricing) {
            return $this->pricing;
        }

        $this->pricing = [];

        try {
            foreach (DataLoader::load() as $provider) {
                foreach ($provider['models'] ?? [] as $id => $model) {
                    $this->pricing[$id] = ['cost' => $model['cost'] ?? null, 'limit' => $model['limit'] ?? null];
                }
            }
        } catch (\Throwable) {
            // `symfony/models-dev` not installed — the detail pane omits pricing.
        }

        return $this->pricing;
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
