<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\HuggingFace;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\ModelCatalog\AbstractModelCatalog;

/**
 * Popular models of Hugging Face Inference Providers, use ModelApiCatalog to resolve any other model.
 *
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class ModelCatalog extends AbstractModelCatalog
{
    /**
     * @param array<string, array{class: string, capabilities: list<Capability>}> $additionalModels
     */
    public function __construct(array $additionalModels = [])
    {
        // STATIC LIST START
        // This list is generated from external metadata. Run dev/update-model-catalogs.php to refresh it.
        $defaultModels = [
            'Alibaba-NLP/gte-reranker-modernbert-base' => [
                'class' => Reranker::class,
                'capabilities' => [
                    Capability::INPUT_MULTIPLE,
                    Capability::RERANKING,
                ],
            ],
            'Arunavaonly/Bangla-twoclass-Sentiment-Analyzer' => [
                'class' => Classification::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'BAAI/bge-base-en' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'BAAI/bge-base-en-v1.5' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'BAAI/bge-base-zh-v1.5' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'BAAI/bge-large-en-v1.5' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'BAAI/bge-large-zh-v1.5' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'BAAI/bge-m3' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'BAAI/bge-reranker-base' => [
                'class' => Classification::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'BAAI/bge-reranker-large' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'BAAI/bge-reranker-v2-m3' => [
                'class' => Classification::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'BAAI/bge-small-en-v1.5' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'BAAI/bge-small-zh-v1.5' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'BSC-LT/ALIA-40b-instruct-2606' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'Babelscape/wikineural-multilingual-ner' => [
                'class' => TokenClassification::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'CohereLabs/aya-expanse-32b' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'CohereLabs/aya-vision-32b' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::INPUT_IMAGE,
                ],
            ],
            'CohereLabs/c4ai-command-a-03-2025' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'CohereLabs/c4ai-command-r-08-2024' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'CohereLabs/c4ai-command-r7b-12-2024' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'CohereLabs/c4ai-command-r7b-arabic-02-2025' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'CohereLabs/command-a-reasoning-08-2025' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'CohereLabs/command-a-translate-08-2025' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'CohereLabs/command-a-vision-07-2025' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'CohereLabs/tiny-aya-earth' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'CohereLabs/tiny-aya-fire' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'CohereLabs/tiny-aya-global' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'CohereLabs/tiny-aya-water' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'Davlan/bert-base-multilingual-cased-ner-hrl' => [
                'class' => TokenClassification::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'Davlan/xlm-roberta-base-ner-hrl' => [
                'class' => TokenClassification::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'ElKulako/cryptobert' => [
                'class' => Classification::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'FacebookAI/roberta-base' => [
                'class' => FillMask::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'FacebookAI/roberta-large' => [
                'class' => FillMask::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'FacebookAI/xlm-roberta-base' => [
                'class' => FillMask::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'FacebookAI/xlm-roberta-large' => [
                'class' => FillMask::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'Falconsai/nsfw_image_detection' => [
                'class' => Classification::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                ],
            ],
            'Falconsai/text_summarization' => [
                'class' => Summarization::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'Freepik/nsfw_image_detector' => [
                'class' => Classification::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                ],
            ],
            'Helsinki-NLP/opus-mt-ROMANCE-en' => [
                'class' => Translation::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'Helsinki-NLP/opus-mt-ar-en' => [
                'class' => Translation::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'Helsinki-NLP/opus-mt-de-en' => [
                'class' => Translation::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'Helsinki-NLP/opus-mt-en-az' => [
                'class' => Translation::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'Helsinki-NLP/opus-mt-en-de' => [
                'class' => Translation::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'Helsinki-NLP/opus-mt-en-es' => [
                'class' => Translation::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'Helsinki-NLP/opus-mt-en-fr' => [
                'class' => Translation::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'Helsinki-NLP/opus-mt-en-ru' => [
                'class' => Translation::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'Helsinki-NLP/opus-mt-en-zh' => [
                'class' => Translation::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'Helsinki-NLP/opus-mt-es-en' => [
                'class' => Translation::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'Helsinki-NLP/opus-mt-fr-en' => [
                'class' => Translation::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'Helsinki-NLP/opus-mt-it-en' => [
                'class' => Translation::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'Helsinki-NLP/opus-mt-ko-en' => [
                'class' => Translation::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'Helsinki-NLP/opus-mt-nl-en' => [
                'class' => Translation::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'Helsinki-NLP/opus-mt-ru-en' => [
                'class' => Translation::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'Helsinki-NLP/opus-mt-tc-big-ko-en' => [
                'class' => Translation::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'Helsinki-NLP/opus-mt-zh-en' => [
                'class' => Translation::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'ISxOdin/vit-base-oxford-iiit-pets' => [
                'class' => Classification::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                ],
            ],
            'IlyaGusev/rut5_base_headline_gen_telegram' => [
                'class' => Summarization::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'IlyaGusev/rut5_base_sum_gazeta' => [
                'class' => Summarization::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'Jean-Baptiste/camembert-ner' => [
                'class' => TokenClassification::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'MilaNLProc/xlm-emo-t' => [
                'class' => Classification::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'MiniMaxAI/MiniMax-M1-80k' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'MiniMaxAI/MiniMax-M2.5' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'MiniMaxAI/MiniMax-M2.7' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'MiniMaxAI/MiniMax-M3' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::INPUT_IMAGE,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'MiniMaxAI/MiniMax-M3.1-Flash' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::INPUT_IMAGE,
                    Capability::TOOL_CALLING,
                ],
            ],
            'MoritzLaurer/DeBERTa-v3-base-mnli-fever-anli' => [
                'class' => ZeroShotClassification::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'MoritzLaurer/DeBERTa-v3-large-mnli-fever-anli-ling-wanli' => [
                'class' => ZeroShotClassification::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'MoritzLaurer/bge-m3-zeroshot-v2.0' => [
                'class' => ZeroShotClassification::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'MoritzLaurer/deberta-v3-base-zeroshot-v1.1-all-33' => [
                'class' => ZeroShotClassification::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'MoritzLaurer/deberta-v3-base-zeroshot-v2.0' => [
                'class' => ZeroShotClassification::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'MoritzLaurer/deberta-v3-large-zeroshot-v2.0' => [
                'class' => ZeroShotClassification::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'MoritzLaurer/deberta-v3-xsmall-zeroshot-v1.1-all-33' => [
                'class' => ZeroShotClassification::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'MoritzLaurer/mDeBERTa-v3-base-mnli-xnli' => [
                'class' => ZeroShotClassification::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'MoritzLaurer/mDeBERTa-v3-base-xnli-multilingual-nli-2mil7' => [
                'class' => ZeroShotClassification::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'MoritzLaurer/multilingual-MiniLMv2-L6-mnli-xnli' => [
                'class' => ZeroShotClassification::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'MoritzLaurer/xtremedistil-l6-h256-zeroshot-v1.1-all-33' => [
                'class' => ZeroShotClassification::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'NeuML/pubmedbert-base-embeddings' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'NousResearch/Hermes-3-Llama-3.1-70B' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'OpenMed/OpenMed-NER-DiseaseDetect-BioMed-335M' => [
                'class' => TokenClassification::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'OpenMed/OpenMed-NER-GenomicDetect-PubMed-109M' => [
                'class' => TokenClassification::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'OpenMed/OpenMed-NER-OrganismDetect-BioMed-109M' => [
                'class' => TokenClassification::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'OpenMed/OpenMed-NER-PharmaDetect-ModernClinical-149M' => [
                'class' => TokenClassification::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'OpenMed/OpenMed-NER-PharmaDetect-SuperClinical-434M' => [
                'class' => TokenClassification::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'OpenMed/OpenMed-PII-SuperClinical-Small-44M-v1' => [
                'class' => TokenClassification::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'OrdalieTech/Solon-embeddings-large-0.1' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'ProsusAI/finbert' => [
                'class' => Classification::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'Qwen/Qwen2.5-72B-Instruct' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'Qwen/Qwen2.5-Coder-32B-Instruct' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'Qwen/Qwen2.5-Coder-3B-Instruct' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'Qwen/Qwen2.5-Coder-7B-Instruct' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'Qwen/Qwen2.5-VL-72B-Instruct' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'Qwen/Qwen3-14B' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'Qwen/Qwen3-235B-A22B' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'Qwen/Qwen3-235B-A22B-Instruct-2507' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'Qwen/Qwen3-235B-A22B-Thinking-2507' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'Qwen/Qwen3-30B-A3B' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'Qwen/Qwen3-32B' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'Qwen/Qwen3-4B-Instruct-2507' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'Qwen/Qwen3-4B-Thinking-2507' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'Qwen/Qwen3-8B' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'Qwen/Qwen3-Next-80B-A3B-Instruct' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'Qwen/Qwen3-Reranker-0.6B' => [
                'class' => Reranker::class,
                'capabilities' => [
                    Capability::INPUT_MULTIPLE,
                    Capability::RERANKING,
                ],
            ],
            'Qwen/Qwen3-VL-235B-A22B-Instruct' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::INPUT_IMAGE,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'Qwen/Qwen3-VL-30B-A3B-Instruct' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::INPUT_IMAGE,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'Qwen/Qwen3-VL-Reranker-2B' => [
                'class' => Reranker::class,
                'capabilities' => [
                    Capability::INPUT_MULTIPLE,
                    Capability::RERANKING,
                ],
            ],
            'Qwen/Qwen3.5-122B-A10B' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::INPUT_IMAGE,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'Qwen/Qwen3.5-27B' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::INPUT_IMAGE,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'Qwen/Qwen3.5-35B-A3B' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::INPUT_IMAGE,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'Qwen/Qwen3.5-397B-A17B' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::INPUT_IMAGE,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'Qwen/Qwen3.5-9B' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::INPUT_IMAGE,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'Qwen/Qwen3.6-27B' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::INPUT_IMAGE,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'Qwen/Qwen3.6-35B-A3B' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::INPUT_IMAGE,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'Qwen/Qwen3.8-2.4T-A95B' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'Qwen/Qwen3.8-27B' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::INPUT_IMAGE,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'SamLowe/roberta-base-go_emotions' => [
                'class' => Classification::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'Sao10K/L3-8B-Stheno-v3.2' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'Snowflake/snowflake-arctic-embed-l-v2.0' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'StanfordAIMI/stanford-deidentifier-base' => [
                'class' => TokenClassification::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'WhereIsAI/UAE-Large-V1' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'XiaomiMiMo/MiMo-V2.5' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'XiaomiMiMo/MiMo-V2.5-Pro' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'XiaomiMiMo/MiMo-V2.6-Flash-RL' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'XiaomiMiMo/MiMo-V2.6-Pro-RL' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'YituTech/conv-bert-base' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'ahotrod/electra_large_discriminator_squad2_512' => [
                'class' => QuestionAnswering::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'albert/albert-base-v2' => [
                'class' => FillMask::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'allenai/specter2_base' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'almanach/camembert-base' => [
                'class' => FillMask::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'alpindale/WizardLM-2-8x22B' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'amunchet/rorshark-vit-base' => [
                'class' => Classification::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                ],
            ],
            'cahya/t5-base-indonesian-summarization-cased' => [
                'class' => Summarization::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'cambridgeltl/SapBERT-from-PubMedBERT-fulltext' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'cardiffnlp/twitter-roberta-base-sentiment-latest' => [
                'class' => Classification::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'cardiffnlp/twitter-xlm-roberta-base-sentiment' => [
                'class' => Classification::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'cl-nagoya/ruri-v3-reranker-310m' => [
                'class' => Reranker::class,
                'capabilities' => [
                    Capability::INPUT_MULTIPLE,
                    Capability::RERANKING,
                ],
            ],
            'cnicu/t5-small-booksum' => [
                'class' => Summarization::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'cointegrated/rubert-base-cased-nli-threeway' => [
                'class' => ZeroShotClassification::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'cross-encoder/mmarco-mMiniLMv2-L12-H384-v1' => [
                'class' => Reranker::class,
                'capabilities' => [
                    Capability::INPUT_MULTIPLE,
                    Capability::RERANKING,
                ],
            ],
            'cross-encoder/ms-marco-MiniLM-L12-v2' => [
                'class' => Reranker::class,
                'capabilities' => [
                    Capability::INPUT_MULTIPLE,
                    Capability::RERANKING,
                ],
            ],
            'cross-encoder/ms-marco-MiniLM-L2-v2' => [
                'class' => Reranker::class,
                'capabilities' => [
                    Capability::INPUT_MULTIPLE,
                    Capability::RERANKING,
                ],
            ],
            'cross-encoder/ms-marco-MiniLM-L4-v2' => [
                'class' => Reranker::class,
                'capabilities' => [
                    Capability::INPUT_MULTIPLE,
                    Capability::RERANKING,
                ],
            ],
            'cross-encoder/ms-marco-MiniLM-L6-v2' => [
                'class' => Reranker::class,
                'capabilities' => [
                    Capability::INPUT_MULTIPLE,
                    Capability::RERANKING,
                ],
            ],
            'cross-encoder/ms-marco-TinyBERT-L2-v2' => [
                'class' => Reranker::class,
                'capabilities' => [
                    Capability::INPUT_MULTIPLE,
                    Capability::RERANKING,
                ],
            ],
            'cross-encoder/ms-marco-TinyBERT-L6' => [
                'class' => Reranker::class,
                'capabilities' => [
                    Capability::INPUT_MULTIPLE,
                    Capability::RERANKING,
                ],
            ],
            'cross-encoder/msmarco-MiniLM-L12-en-de-v1' => [
                'class' => Reranker::class,
                'capabilities' => [
                    Capability::INPUT_MULTIPLE,
                    Capability::RERANKING,
                ],
            ],
            'cross-encoder/stsb-distilroberta-base' => [
                'class' => Reranker::class,
                'capabilities' => [
                    Capability::INPUT_MULTIPLE,
                    Capability::RERANKING,
                ],
            ],
            'cross-encoder/stsb-roberta-base' => [
                'class' => Reranker::class,
                'capabilities' => [
                    Capability::INPUT_MULTIPLE,
                    Capability::RERANKING,
                ],
            ],
            'cross-encoder/stsb-roberta-large' => [
                'class' => Reranker::class,
                'capabilities' => [
                    Capability::INPUT_MULTIPLE,
                    Capability::RERANKING,
                ],
            ],
            'csarron/bert-base-uncased-squad-v1' => [
                'class' => QuestionAnswering::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'csarron/mobilebert-uncased-squad-v2' => [
                'class' => QuestionAnswering::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'csebuetnlp/mT5_multilingual_XLSum' => [
                'class' => Summarization::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'd0rj/rut5-base-summ' => [
                'class' => Summarization::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'datasocietyco/bge-base-en-v1.5-course-recommender-v5' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'dbmdz/bert-large-cased-finetuned-conll03-english' => [
                'class' => TokenClassification::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'deepseek-ai/DeepSeek-R1' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'deepseek-ai/DeepSeek-R1-0528' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'deepseek-ai/DeepSeek-R1-Distill-Llama-8B' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'deepseek-ai/DeepSeek-R1-Distill-Qwen-14B' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'deepseek-ai/DeepSeek-R1-Distill-Qwen-7B' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'deepseek-ai/DeepSeek-V3' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'deepseek-ai/DeepSeek-V3-0324' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'deepseek-ai/DeepSeek-V3.1' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'deepseek-ai/DeepSeek-V3.1-Terminus' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'deepseek-ai/DeepSeek-V3.2' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'deepseek-ai/DeepSeek-V4-Flash' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'deepseek-ai/DeepSeek-V4-Flash-0731' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'deepseek-ai/DeepSeek-V4-Flash-Vision-Exp' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::INPUT_IMAGE,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'deepseek-ai/DeepSeek-V4-Pro' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'deepseek-ai/DeepSeek-V4-Pro-0813' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'deepseek-ai/DeepSeek-V4.1-Flash' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::INPUT_IMAGE,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'deepseek-ai/DeepSeek-V4.1-Flash-1017' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::INPUT_IMAGE,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'deepset/bert-base-cased-squad2' => [
                'class' => QuestionAnswering::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'deepset/bert-large-uncased-whole-word-masking-squad2' => [
                'class' => QuestionAnswering::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'deepset/bert-medium-squad2-distilled' => [
                'class' => QuestionAnswering::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'deepset/deberta-v3-base-squad2' => [
                'class' => QuestionAnswering::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'deepset/deberta-v3-large-squad2' => [
                'class' => QuestionAnswering::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'deepset/minilm-uncased-squad2' => [
                'class' => QuestionAnswering::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'deepset/roberta-base-squad2' => [
                'class' => QuestionAnswering::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'deepset/roberta-large-squad2' => [
                'class' => QuestionAnswering::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'deepset/tinyroberta-squad2' => [
                'class' => QuestionAnswering::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'deepset/xlm-roberta-base-squad2' => [
                'class' => QuestionAnswering::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'deepset/xlm-roberta-large-squad2' => [
                'class' => QuestionAnswering::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'dima806/fairface_age_image_detection' => [
                'class' => Classification::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                ],
            ],
            'dima806/man_woman_face_image_detection' => [
                'class' => Classification::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                ],
            ],
            'distilbert/distilbert-base-cased-distilled-squad' => [
                'class' => QuestionAnswering::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'distilbert/distilbert-base-uncased' => [
                'class' => FillMask::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'distilbert/distilbert-base-uncased-distilled-squad' => [
                'class' => QuestionAnswering::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'distilbert/distilbert-base-uncased-finetuned-sst-2-english' => [
                'class' => Classification::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'distilbert/distilroberta-base' => [
                'class' => FillMask::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'dragonkue/bge-reranker-v2-m3-ko' => [
                'class' => Reranker::class,
                'capabilities' => [
                    Capability::INPUT_MULTIPLE,
                    Capability::RERANKING,
                ],
            ],
            'dslim/bert-base-NER' => [
                'class' => TokenClassification::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'dslim/bert-large-NER' => [
                'class' => TokenClassification::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'emilyalsentzer/Bio_ClinicalBERT' => [
                'class' => FillMask::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'facebook/bart-large-cnn' => [
                'class' => Summarization::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'facebook/bart-large-mnli' => [
                'class' => ZeroShotClassification::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'facebook/bart-large-xsum' => [
                'class' => Summarization::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'facebook/convnextv2-base-22k-384' => [
                'class' => Classification::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                ],
            ],
            'facebook/convnextv2-tiny-22k-224' => [
                'class' => Classification::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                ],
            ],
            'facebook/deit-tiny-patch16-224' => [
                'class' => Classification::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                ],
            ],
            'facebook/detr-resnet-101' => [
                'class' => ObjectDetection::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                ],
            ],
            'facebook/detr-resnet-50' => [
                'class' => ObjectDetection::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                ],
            ],
            'facebook/esm2_t33_650M_UR50D' => [
                'class' => FillMask::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'facebook/mask2former-swin-large-ade-semantic' => [
                'class' => ImageSegmentation::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                ],
            ],
            'facebook/mask2former-swin-large-cityscapes-semantic' => [
                'class' => ImageSegmentation::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                ],
            ],
            'facebook/mask2former-swin-large-coco-instance' => [
                'class' => ImageSegmentation::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                ],
            ],
            'facebook/mask2former-swin-large-mapillary-vistas-semantic' => [
                'class' => ImageSegmentation::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                ],
            ],
            'facebook/mask2former-swin-small-coco-instance' => [
                'class' => ImageSegmentation::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                ],
            ],
            'facebook/mask2former-swin-tiny-coco-instance' => [
                'class' => ImageSegmentation::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                ],
            ],
            'fashn-ai/fashn-human-parser' => [
                'class' => ImageSegmentation::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                ],
            ],
            'giacomoarienti/nsfw-classifier' => [
                'class' => Classification::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                ],
            ],
            'google-bert/bert-base-cased' => [
                'class' => FillMask::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'google-bert/bert-base-chinese' => [
                'class' => FillMask::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'google-bert/bert-base-multilingual-cased' => [
                'class' => FillMask::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'google-bert/bert-base-multilingual-uncased' => [
                'class' => FillMask::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'google-bert/bert-base-uncased' => [
                'class' => FillMask::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'google-bert/bert-large-uncased-whole-word-masking-finetuned-squad' => [
                'class' => QuestionAnswering::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'google-t5/t5-base' => [
                'class' => Translation::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'google-t5/t5-large' => [
                'class' => Translation::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'google-t5/t5-small' => [
                'class' => Translation::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'google/bigbird-pegasus-large-arxiv' => [
                'class' => Summarization::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'google/embeddinggemma-300m' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'google/gemma-3-12b-it' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::INPUT_IMAGE,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'google/gemma-3-27b-it' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::INPUT_IMAGE,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'google/gemma-3-4b-it' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::INPUT_IMAGE,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'google/gemma-4-26B-A4B-it' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::INPUT_IMAGE,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'google/gemma-4-31B-it' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::INPUT_IMAGE,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'google/pegasus-cnn_dailymail' => [
                'class' => Summarization::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'google/pegasus-large' => [
                'class' => Summarization::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'google/pegasus-xsum' => [
                'class' => Summarization::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'google/tapas-base-finetuned-wtq' => [
                'class' => TableQuestionAnswering::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'google/tapas-large-finetuned-wtq' => [
                'class' => TableQuestionAnswering::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'google/vit-base-patch16-224' => [
                'class' => Classification::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                ],
            ],
            'hotchpotch/japanese-reranker-cross-encoder-small-v1' => [
                'class' => Reranker::class,
                'capabilities' => [
                    Capability::INPUT_MULTIPLE,
                    Capability::RERANKING,
                ],
            ],
            'ibm-granite/granite-4.2-30b' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'ibm-granite/granite-4.2-3b' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'ibm-granite/granite-4.2-8b' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'iiiorg/piiranha-v1-detect-personal-information' => [
                'class' => TokenClassification::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'inclusionAI/Ling-3.0-flash' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'inclusionAI/Ling-3.0-flash-Fin' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'inclusionAI/Ling-3.0-flash-VL' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::INPUT_IMAGE,
                    Capability::TOOL_CALLING,
                ],
            ],
            'inclusionAI/Ling-3.1-flash' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'intfloat/e5-base-v2' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'intfloat/e5-small-v2' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'intfloat/multilingual-e5-base' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'intfloat/multilingual-e5-large' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'intfloat/multilingual-e5-large-instruct' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'intfloat/multilingual-e5-small' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'j-hartmann/emotion-english-distilroberta-base' => [
                'class' => Classification::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'joeddav/xlm-roberta-large-xnli' => [
                'class' => ZeroShotClassification::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'jonathandinu/face-parsing' => [
                'class' => ImageSegmentation::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                ],
            ],
            'knkarthick/MEETING_SUMMARY' => [
                'class' => Summarization::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'kredor/punctuate-all' => [
                'class' => TokenClassification::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'livekit/turn-detector' => [
                'class' => Classification::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'lxyuan/distilbert-base-multilingual-cased-sentiments-student' => [
                'class' => Classification::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'mattmdjaga/segformer_b2_clothes' => [
                'class' => ImageSegmentation::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                ],
            ],
            'meta-llama/Llama-3.1-8B-Instruct' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'meta-llama/Llama-3.3-70B-Instruct' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'meta-llama/Llama-4-Maverick-17B-128E-Instruct-FP8' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'meta-llama/Llama-4-Scout-17B-16E-Instruct' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::INPUT_IMAGE,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'meta-llama/Llama-Guard-4-12B' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::INPUT_IMAGE,
                ],
            ],
            'meta-llama/Prompt-Guard-86M' => [
                'class' => Classification::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'meta-models/Muse-Glimmer-30B' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::INPUT_IMAGE,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'meta-models/Muse-Spark-Open-816B-A42B' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::INPUT_IMAGE,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'meta-models/Muse-Spark-Open-816B-A42B-NVFP4' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::INPUT_IMAGE,
                    Capability::TOOL_CALLING,
                ],
            ],
            'microsoft/deberta-v3-base' => [
                'class' => FillMask::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'microsoft/deberta-v3-large' => [
                'class' => FillMask::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'microsoft/deberta-v3-small' => [
                'class' => FillMask::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'microsoft/dit-base-finetuned-rvlcdip' => [
                'class' => Classification::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                ],
            ],
            'microsoft/llmlingua-2-bert-base-multilingual-cased-meetingbank' => [
                'class' => TokenClassification::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'microsoft/mdeberta-v3-base' => [
                'class' => FillMask::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'microsoft/phi-4' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'microsoft/resnet-18' => [
                'class' => Classification::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                ],
            ],
            'microsoft/resnet-50' => [
                'class' => Classification::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                ],
            ],
            'microsoft/swinv2-tiny-patch4-window16-256' => [
                'class' => Classification::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                ],
            ],
            'mixedbread-ai/deepset-mxbai-embed-de-large-v1' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'mixedbread-ai/mxbai-embed-large-v1' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'mixedbread-ai/mxbai-rerank-base-v1' => [
                'class' => Reranker::class,
                'capabilities' => [
                    Capability::INPUT_MULTIPLE,
                    Capability::RERANKING,
                ],
            ],
            'mixedbread-ai/mxbai-rerank-large-v1' => [
                'class' => Reranker::class,
                'capabilities' => [
                    Capability::INPUT_MULTIPLE,
                    Capability::RERANKING,
                ],
            ],
            'mixedbread-ai/mxbai-rerank-xsmall-v1' => [
                'class' => Reranker::class,
                'capabilities' => [
                    Capability::INPUT_MULTIPLE,
                    Capability::RERANKING,
                ],
            ],
            'monologg/koelectra-base-v3-finetuned-korquad' => [
                'class' => QuestionAnswering::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'monologg/koelectra-small-v2-distilled-korquad-384' => [
                'class' => QuestionAnswering::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'moonshotai/Kimi-K2-Instruct' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'moonshotai/Kimi-K2-Instruct-0905' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'moonshotai/Kimi-K2.5' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::INPUT_IMAGE,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'moonshotai/Kimi-K2.6' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::INPUT_IMAGE,
                    Capability::TOOL_CALLING,
                ],
            ],
            'moonshotai/Kimi-K2.7-Code' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::INPUT_IMAGE,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'moonshotai/Kimi-K3' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::INPUT_IMAGE,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'mrm8488/bert-mini2bert-mini-finetuned-cnn_daily_mail-summarization' => [
                'class' => Summarization::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'nateraw/vit-age-classifier' => [
                'class' => Classification::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                ],
            ],
            'ncbi/MedCPT-Cross-Encoder' => [
                'class' => Classification::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'nlpai-lab/KURE-v1' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'nlpaueb/legal-bert-base-uncased' => [
                'class' => FillMask::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'nlptown/bert-base-multilingual-uncased-sentiment' => [
                'class' => Classification::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'nvidia/NVIDIA-Nemotron-3-Ultra-550B-A55B-BF16' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'nvidia/NVIDIA-Nemotron-3-Ultra-550B-A55B-NVFP4' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'nvidia/NVIDIA-Nemotron-3.5-Lightning-30B-A3B-BF16' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'nvidia/NVIDIA-Nemotron-3.5-Super-VL-120B-A12B-NVFP4' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::INPUT_IMAGE,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'nvidia/dragon-multiturn-context-encoder' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'nvidia/dragon-multiturn-query-encoder' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'nvidia/mit-b0' => [
                'class' => Classification::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                ],
            ],
            'nvidia/segformer-b0-finetuned-ade-512-512' => [
                'class' => ImageSegmentation::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                ],
            ],
            'nvidia/segformer-b1-finetuned-ade-512-512' => [
                'class' => ImageSegmentation::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                ],
            ],
            'nvidia/segformer-b2-finetuned-ade-512-512' => [
                'class' => ImageSegmentation::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                ],
            ],
            'nvidia/segformer-b3-finetuned-ade-512-512' => [
                'class' => ImageSegmentation::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                ],
            ],
            'nvidia/segformer-b4-finetuned-ade-512-512' => [
                'class' => ImageSegmentation::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                ],
            ],
            'nvidia/segformer-b5-finetuned-ade-640-640' => [
                'class' => ImageSegmentation::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                ],
            ],
            'nvidia/segformer-b5-finetuned-cityscapes-1024-1024' => [
                'class' => ImageSegmentation::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                ],
            ],
            'obi/deid_roberta_i2b2' => [
                'class' => TokenClassification::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'oliverguhr/fullstop-punctuation-multilang-large' => [
                'class' => TokenClassification::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'oliverguhr/german-sentiment-bert' => [
                'class' => Classification::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'openai-community/roberta-base-openai-detector' => [
                'class' => Classification::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'openai/gpt-oss-120b' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'openai/gpt-oss-20b' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'openai/gpt-oss-safeguard-20b' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'openai/whisper-large-v3' => [
                'class' => SpeechRecognition::class,
                'capabilities' => [
                    Capability::INPUT_AUDIO,
                    Capability::OUTPUT_TEXT,
                    Capability::SPEECH_TO_TEXT,
                ],
            ],
            'openai/whisper-large-v3-turbo' => [
                'class' => SpeechRecognition::class,
                'capabilities' => [
                    Capability::INPUT_AUDIO,
                    Capability::OUTPUT_TEXT,
                    Capability::SPEECH_TO_TEXT,
                ],
            ],
            'philschmid/bart-large-cnn-samsum' => [
                'class' => Summarization::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'prism-ml/Ternary-Bonsai-27B-AWQ-4bit' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::INPUT_IMAGE,
                    Capability::TOOL_CALLING,
                ],
            ],
            'prism-ml/Ternary-Bonsai-27B-gguf' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'prithivMLmods/Gender-Classifier-Mini' => [
                'class' => Classification::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                ],
            ],
            'protectai/deberta-v3-base-prompt-injection-v2' => [
                'class' => Classification::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'reflection/Beam-1.0-501B-A23B' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'reflection/Beam-1.0-501B-A23B-NVFP4' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'rizvandwiki/gender-classification' => [
                'class' => Classification::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                ],
            ],
            'rizvandwiki/gender-classification-2' => [
                'class' => Classification::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                ],
            ],
            'sentence-transformers/all-MiniLM-L12-v2' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'sentence-transformers/all-MiniLM-L6-v2' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'sentence-transformers/all-distilroberta-v1' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'sentence-transformers/all-mpnet-base-v2' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'sentence-transformers/distiluse-base-multilingual-cased-v1' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'sentence-transformers/multi-qa-mpnet-base-dot-v1' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'sentence-transformers/paraphrase-MiniLM-L6-v2' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'sentence-transformers/paraphrase-mpnet-base-v2' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'sentence-transformers/paraphrase-multilingual-MiniLM-L12-v2' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'sentence-transformers/paraphrase-multilingual-mpnet-base-v2' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'shi-labs/oneformer_ade20k_swin_large' => [
                'class' => ImageSegmentation::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                ],
            ],
            'shi-labs/oneformer_ade20k_swin_tiny' => [
                'class' => ImageSegmentation::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                ],
            ],
            'shi-labs/oneformer_cityscapes_swin_large' => [
                'class' => ImageSegmentation::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                ],
            ],
            'shi-labs/oneformer_coco_swin_large' => [
                'class' => ImageSegmentation::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                ],
            ],
            'sileod/deberta-v3-base-tasksource-nli' => [
                'class' => ZeroShotClassification::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'speakleash/Bielik-11B-v3.0-Instruct' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'sshleifer/distilbart-cnn-12-6' => [
                'class' => Summarization::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'sshleifer/distilbart-cnn-6-6' => [
                'class' => Summarization::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'sshleifer/distilbart-xsum-12-6' => [
                'class' => Summarization::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'stabilityai/stable-diffusion-3-medium-diffusers' => [
                'class' => TextToImage::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_IMAGE,
                    Capability::TEXT_TO_IMAGE,
                ],
            ],
            'stepfun-ai/Step-3.5-Flash' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'stepfun-ai/Step-3.7-Flash' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::INPUT_IMAGE,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'swiss-ai/Apertus-70B-Instruct-2509' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'swiss-ai/Apertus-8B-Instruct-2509' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'tasksource/ModernBERT-base-nli' => [
                'class' => ZeroShotClassification::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'tasksource/deberta-small-long-nli' => [
                'class' => ZeroShotClassification::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'tencent/Hy3' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'tencent/Hy4-preview' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'thenlper/gte-small' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'thinkingmachines/Inkling' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::INPUT_IMAGE,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'thinkingmachines/Inkling-Small' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::INPUT_IMAGE,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'timpal0l/mdeberta-v3-base-squad2' => [
                'class' => QuestionAnswering::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'togethercomputer/Tev1-4B-experimental' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'typeform/distilbert-base-uncased-mnli' => [
                'class' => ZeroShotClassification::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'unitary/unbiased-toxic-roberta' => [
                'class' => Classification::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'valhalla/distilbart-mnli-12-1' => [
                'class' => ZeroShotClassification::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'valhalla/distilbart-mnli-12-3' => [
                'class' => ZeroShotClassification::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'w11wo/indonesian-roberta-base-posp-tagger' => [
                'class' => TokenClassification::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'yatharth97/T5-base-10K-summarization' => [
                'class' => Summarization::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'zai-org/AutoGLM-Phone-9B-Multilingual' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::INPUT_IMAGE,
                ],
            ],
            'zai-org/GLM-4.5-Air' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'zai-org/GLM-4.5V' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::INPUT_IMAGE,
                    Capability::TOOL_CALLING,
                ],
            ],
            'zai-org/GLM-4.5V-FP8' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::INPUT_IMAGE,
                    Capability::TOOL_CALLING,
                ],
            ],
            'zai-org/GLM-4.6' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'zai-org/GLM-4.6V' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::INPUT_IMAGE,
                    Capability::TOOL_CALLING,
                ],
            ],
            'zai-org/GLM-4.6V-FP8' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::INPUT_IMAGE,
                    Capability::TOOL_CALLING,
                ],
            ],
            'zai-org/GLM-4.6V-Flash' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::INPUT_IMAGE,
                    Capability::TOOL_CALLING,
                ],
            ],
            'zai-org/GLM-4.7' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'zai-org/GLM-4.7-FP8' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'zai-org/GLM-4.7-Flash' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'zai-org/GLM-5' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'zai-org/GLM-5.1' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'zai-org/GLM-5.2' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'zai-org/GLM-5.2-FP8' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'zai-org/GLM-5.3' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'zai-org/GLM-5.3-BF16' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'zai-org/GLM-5.3-Flash' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::INPUT_IMAGE,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'zai-org/GLM-5.3-Flash-BF16' => [
                'class' => Completions::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::INPUT_IMAGE,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
        ];
        // STATIC LIST END

        $this->models = array_merge($defaultModels, $additionalModels);
    }
}
