<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Cookbook\Exception;

use App\Cookbook\Exception\CookbookArticleNotFoundException;
use App\Cookbook\Exception\CookbookGenerationException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

final class CookbookExceptionTest extends TestCase
{
    public function testArticleNotFoundMapsToHttp404()
    {
        $this->assertSame(Response::HTTP_NOT_FOUND, $this->httpStatusOf(CookbookArticleNotFoundException::class));
    }

    public function testGenerationFailureMapsToHttp503()
    {
        $this->assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $this->httpStatusOf(CookbookGenerationException::class));
    }

    private function httpStatusOf(string $exceptionClass): int
    {
        $attributes = (new \ReflectionClass($exceptionClass))->getAttributes(WithHttpStatus::class);
        $this->assertCount(1, $attributes, \sprintf('%s should declare #[WithHttpStatus].', $exceptionClass));

        return $attributes[0]->newInstance()->statusCode;
    }
}
