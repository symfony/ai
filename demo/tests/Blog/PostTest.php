<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Blog;

use App\Blog\Post;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class PostTest extends TestCase
{
    public function testPostToString()
    {
        $post = new Post(
            Uuid::v4(),
            'Hello, World!',
            'https://example.com/hello-world',
            'This is a test description.',
            'This is a test post.',
            'John Doe',
            new \DateTimeImmutable('2024-12-08 09:39:00'),
        );

        $expected = <<<TEXT
            Title: Hello, World!
            From: John Doe on 2024-12-08
            Description: This is a test description.
            This is a test post.
            TEXT;

        $this->assertSame($expected, $post->toString());
    }

    public function testPostToArray()
    {
        $id = Uuid::v4();
        $post = new Post(
            $id,
            'Hello, World!',
            'https://example.com/hello-world',
            'This is a test description.',
            'This is a test post.',
            'John Doe',
            new \DateTimeImmutable('2024-12-08 09:39:00'),
        );

        $expected = [
            'id' => $id->toRfc4122(),
            'title' => 'Hello, World!',
            'link' => 'https://example.com/hello-world',
            'description' => 'This is a test description.',
            'content' => 'This is a test post.',
            'author' => 'John Doe',
            'date' => '2024-12-08',
        ];

        $this->assertSame($expected, $post->toArray());
    }
}
