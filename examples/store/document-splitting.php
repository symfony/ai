<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Store\Document\Loader\TextFileLoader;
use Symfony\AI\Store\Document\Transformer\TextSplitTransformer;

require_once dirname(__DIR__).'/bootstrap.php';

$loader = new TextFileLoader();
$splitter = new TextSplitTransformer();
$source = dirname(__DIR__, 2).'/fixtures/lorem.txt';

$documents = iterator_to_array($splitter($loader($source)));

dump($documents);
