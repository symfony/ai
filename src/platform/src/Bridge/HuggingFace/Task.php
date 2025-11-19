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

/**
 * Based on the facets listed at https://huggingface.co/models.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface Task
{
    // Multimodal
    public const AUDIO_TEXT_TO_TEXT = 'audio-text-to-text';
    public const IMAGE_TEXT_TO_TEXT = 'image-text-to-text';
    public const VISUAL_QUESTION_ANSWERING = 'visual-question-answering';
    public const DOCUMENT_QUESTION_ANSWERING = 'document-question-answering';
    public const VIDEO_TEXT_TO_TEXT = 'video-text-to-text';
    public const VISUAL_DOCUMENT_RETRIEVAL = 'visual-document-retrieval';
    public const ANY_TO_ANY = 'any-to-any';

    // Computer Vision
    public const DEPTH_ESTIMATION = 'depth-estimation';
    public const IMAGE_CLASSIFICATION = 'image-classification';
    public const OBJECT_DETECTION = 'object-detection';
    public const IMAGE_SEGMENTATION = 'image-segmentation';
    public const TEXT_TO_IMAGE = 'text-to-image';
    public const IMAGE_TO_TEXT = 'image-to-text';
    public const IMAGE_TO_IMAGE = 'image-to-image';
    public const IMAGE_TO_VIDEO = 'image-to-video';
    public const UNCONDITIONAL_IMAGE_GENERATION = 'unconditional-image-generation';
    public const VIDEO_CLASSIFICATION = 'video-classification';
    public const TEXT_TO_VIDEO = 'text-to-video';
    public const ZERO_SHOT_IMAGE_CLASSIFICATION = 'zero-shot-image-classification';
    public const MASK_GENERATION = 'mask-generation';
    public const ZERO_SHOT_OBJECT_DETECTION = 'zero-shot-object-detection';
    public const TEXT_TO_3D = 'text-to-3d';
    public const IMAGE_TO_3D = 'image-to-3d';
    public const IMAGE_FEATURE_EXTRACTION = 'image-feature-extraction';
    public const KEYPOINT_DETECTION = 'keypoint-detection';
    public const VIDEO_TO_VIDEO = 'video-to-video';

    // Natural Language Processing
    public const TEXT_CLASSIFICATION = 'text-classification';
    public const TOKEN_CLASSIFICATION = 'token-classification';
    public const TABLE_QUESTION_ANSWERING = 'table-question-answering';
    public const QUESTION_ANSWERING = 'question-answering';
    public const ZERO_SHOT_CLASSIFICATION = 'zero-shot-classification';
    public const TRANSLATION = 'translation';
    public const SUMMARIZATION = 'summarization';
    public const FEATURE_EXTRACTION = 'feature-extraction';
    public const TEXT_GENERATION = 'text-generation';
    public const FILL_MASK = 'fill-mask';
    public const SENTENCE_SIMILARITY = 'sentence-similarity';
    public const TEXT_RANKING = 'text-ranking';

    // Audio
    public const TEXT_TO_SPEECH = 'text-to-speech';
    public const TEXT_TO_AUDIO = 'text-to-audio';
    public const AUTOMATIC_SPEECH_RECOGNITION = 'automatic-speech-recognition';
    public const AUDIO_TO_AUDIO = 'audio-to-audio';
    public const AUDIO_CLASSIFICATION = 'audio-classification';
    public const VOICE_ACTIVITY_DETECTION = 'voice-activity-detection';

    // Tabular
    public const TABULAR_CLASSIFICATION = 'tabular-classification';
    public const TABULAR_REGRESSION = 'tabular-regression';
    public const TIME_SERIES_FORECASTING = 'time-series-forecasting';

    // Reinforcement Learning
    public const REINFORCEMENT_LEARNING = 'reinforcement-learning';
    public const ROBOTICS = 'robotics';

    // Other
    public const GRAPH_MACHINE_LEARNING = 'graph-machine-learning';
    public const CHAT_COMPLETION = 'chat-completion';
}
