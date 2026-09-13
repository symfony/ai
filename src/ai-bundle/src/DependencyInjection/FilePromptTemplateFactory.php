<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AiBundle\DependencyInjection;

use Symfony\AI\Platform\Message\Content\File;
use Symfony\AI\Platform\Message\Template;

/**
 * @author mikemikimike
 */
final class FilePromptTemplateFactory
{
    public static function create(string $path): Template
    {
        return Template::string(File::fromFile($path)->asBinary());
    }
}
