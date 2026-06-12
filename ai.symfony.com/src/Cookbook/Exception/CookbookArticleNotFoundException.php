<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Cookbook\Exception;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
#[WithHttpStatus(Response::HTTP_NOT_FOUND)]
final class CookbookArticleNotFoundException extends \RuntimeException
{
}
