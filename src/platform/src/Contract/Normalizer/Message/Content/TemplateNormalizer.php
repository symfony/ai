<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Contract\Normalizer\Message\Content;

use Symfony\AI\Platform\Message\Template;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Normalizes a {@see Template} reached without template variables (when variables are provided, the
 * {@see \Symfony\AI\Platform\EventListener\TemplateRendererListener} replaces it with a rendered
 * {@see \Symfony\AI\Platform\Message\Content\Text} before serialization).
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class TemplateNormalizer implements NormalizerInterface
{
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Template;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            Template::class => true,
        ];
    }

    /**
     * @param Template $data
     *
     * @return array{type: 'template', template: string, template_type: string}
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        return [
            'type' => 'template',
            'template' => $data->getTemplate(),
            'template_type' => $data->getType(),
        ];
    }
}
