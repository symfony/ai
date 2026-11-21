<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\PlatformBridge\Filter;

use App\PlatformBridge\Taxonomy\Capability;
use App\PlatformBridge\Taxonomy\Deployment;
use App\PlatformBridge\Taxonomy\ModelAccess;

/**
 * Common needs, each one a ready-made combination of facet options.
 *
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
enum Preset: string
{
    case Agents = 'agents';
    case Rag = 'rag';
    case Voice = 'voice';
    case ImageGeneration = 'image-generation';
    case Offline = 'offline';
    case NoLockIn = 'no-lock-in';

    public function label(): string
    {
        return match ($this) {
            self::Agents => 'AI agents',
            self::Rag => 'RAG & semantic search',
            self::Voice => 'Voice apps',
            self::ImageGeneration => 'Image generation',
            self::Offline => 'Offline & private',
            self::NoLockIn => 'No vendor lock-in',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Agents => 'Tool calling and structured output, the pillars of agents.',
            self::Rag => 'Embeddings to index your content and search it semantically.',
            self::Voice => 'Speech-to-text and text-to-speech.',
            self::ImageGeneration => 'Create images from a prompt.',
            self::Offline => 'Models running on your own hardware, prompts never leave it.',
            self::NoLockIn => 'Models from several makers behind a single bridge.',
        };
    }

    public function icon(): string
    {
        return 'tabler:'.match ($this) {
            self::Agents => 'robot',
            self::Rag => 'database-search',
            self::Voice => 'microphone',
            self::ImageGeneration => 'photo-ai',
            self::Offline => 'wifi-off',
            self::NoLockIn => 'arrows-shuffle',
        };
    }

    /**
     * @return array<string, list<string>> option values indexed by facet value
     */
    public function getSelection(): array
    {
        return match ($this) {
            self::Agents => [Facet::Capability->value => [Capability::ToolCalling->value, Capability::StructuredOutput->value]],
            self::Rag => [Facet::Capability->value => [Capability::Embeddings->value]],
            self::Voice => [Facet::Capability->value => [Capability::SpeechToText->value, Capability::TextToSpeech->value]],
            self::ImageGeneration => [Facet::Capability->value => [Capability::ImageGeneration->value]],
            self::Offline => [Facet::Deployment->value => [Deployment::Local->value]],
            self::NoLockIn => [Facet::ModelAccess->value => [ModelAccess::MultiVendor->value]],
        };
    }
}
