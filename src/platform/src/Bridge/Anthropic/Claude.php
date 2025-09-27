<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Anthropic;

use Symfony\AI\Platform\Model;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
class Claude extends Model
{
    public const HAIKU_3 = 'claude-3-haiku-20240307';
    public const HAIKU_35 = 'claude-3-5-haiku-latest';
    public const SONNET_3 = 'claude-3-sonnet-20240229';
    public const SONNET_35 = 'claude-3-5-sonnet-latest';
    public const SONNET_37 = 'claude-3-7-sonnet-latest';
    public const SONNET_4 = 'claude-sonnet-4-20250514';
    public const SONNET_4_0 = 'claude-sonnet-4-0';
    public const OPUS_3 = 'claude-3-opus-20240229';
    public const OPUS_4 = 'claude-opus-4-20250514';
    public const OPUS_4_0 = 'claude-opus-4-0';
    public const OPUS_4_1 = 'claude-opus-4-1';

    /**
     * @param array<string, mixed> $options The default options for the model usage
     */
    public function __construct(string $name, array $capabilities = [], array $options = [])
    {
        if (!isset($options['max_tokens'])) {
            $options['max_tokens'] = 1000;
        }

        parent::__construct($name, $capabilities, $options);
    }
}
