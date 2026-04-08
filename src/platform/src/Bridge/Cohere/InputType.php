<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Cohere;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
enum InputType: string
{
    case SearchDocument = 'search_document';
    case SearchQuery = 'search_query';
    case Classification = 'classification';
    case Clustering = 'clustering';
    case Image = 'image';
}
