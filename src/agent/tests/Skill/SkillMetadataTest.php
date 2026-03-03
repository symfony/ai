<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Skill;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Agent\Skill\SkillMetadata;

final class SkillMetadataTest extends TestCase
{
    public function testConstructorWithValidKebabCaseName()
    {
        $metadata = new SkillMetadata('my-skill', 'A useful skill');

        $this->assertSame('my-skill', $metadata->getName());
        $this->assertSame('A useful skill', $metadata->getDescription());
    }

    public function testConstructorWithSingleWordName()
    {
        $metadata = new SkillMetadata('skill', 'A simple skill');

        $this->assertSame('skill', $metadata->getName());
    }

    public function testConstructorWithMultiSegmentKebabCase()
    {
        $metadata = new SkillMetadata('my-very-long-skill', 'A skill with many segments');

        $this->assertSame('my-very-long-skill', $metadata->getName());
    }

    public function testConstructorThrowsOnEmptyName()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be non-empty kebab-case');

        new SkillMetadata('', 'A description');
    }

    public function testConstructorThrowsOnUpperCaseName()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be non-empty kebab-case');

        new SkillMetadata('My-Skill', 'A description');
    }

    public function testConstructorThrowsOnUnderscoreName()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be non-empty kebab-case');

        new SkillMetadata('my_skill', 'A description');
    }

    public function testConstructorThrowsOnNameWithSpaces()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be non-empty kebab-case');

        new SkillMetadata('my skill', 'A description');
    }

    public function testConstructorThrowsOnTrailingDash()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be non-empty kebab-case');

        new SkillMetadata('my-skill-', 'A description');
    }

    public function testConstructorThrowsOnEmptyDescription()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Skill description must not be empty');

        new SkillMetadata('my-skill', '');
    }

    public function testConstructorThrowsOnWhitespaceOnlyDescription()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Skill description must not be empty');

        new SkillMetadata('my-skill', '   ');
    }

    public function testGetLicenseReturnsNullByDefault()
    {
        $metadata = new SkillMetadata('my-skill', 'desc');

        $this->assertNull($metadata->getLicense());
    }

    public function testGetLicenseReturnsSpdxIdentifier()
    {
        $metadata = new SkillMetadata('my-skill', 'desc', license: 'MIT');

        $this->assertSame('MIT', $metadata->getLicense());
    }

    public function testGetAllowedToolsReturnsEmptyArrayByDefault()
    {
        $metadata = new SkillMetadata('my-skill', 'desc');

        $this->assertSame([], $metadata->getAllowedTools());
    }

    public function testGetAllowedToolsReturnsList()
    {
        $metadata = new SkillMetadata('my-skill', 'desc', allowedTools: ['Read', 'Write', 'Bash']);

        $this->assertSame(['Read', 'Write', 'Bash'], $metadata->getAllowedTools());
    }

    public function testGetCompatibilityReturnsNullByDefault()
    {
        $metadata = new SkillMetadata('my-skill', 'desc');

        $this->assertNull($metadata->getCompatibility());
    }

    public function testGetCompatibilityReturnsString()
    {
        $metadata = new SkillMetadata('my-skill', 'desc', compatibility: 'claude >=3.5');

        $this->assertSame('claude >=3.5', $metadata->getCompatibility());
    }

    public function testGetMetadataReturnsEmptyArrayByDefault()
    {
        $metadata = new SkillMetadata('my-skill', 'desc');

        $this->assertSame([], $metadata->getMetadata());
    }

    public function testGetAuthorReturnsNullWhenNotSet()
    {
        $metadata = new SkillMetadata('my-skill', 'desc');

        $this->assertNull($metadata->getAuthor());
    }

    public function testGetAuthorReturnsValueFromMetadata()
    {
        $metadata = new SkillMetadata('my-skill', 'desc', metadata: ['author' => 'Jane Doe']);

        $this->assertSame('Jane Doe', $metadata->getAuthor());
    }

    public function testGetVersionReturnsNullWhenNotSet()
    {
        $metadata = new SkillMetadata('my-skill', 'desc');

        $this->assertNull($metadata->getVersion());
    }

    public function testGetVersionReturnsValueFromMetadata()
    {
        $metadata = new SkillMetadata('my-skill', 'desc', metadata: ['version' => '1.2.0']);

        $this->assertSame('1.2.0', $metadata->getVersion());
    }

    public function testFullMetadata()
    {
        $metadata = new SkillMetadata(
            name: 'pdf-processing',
            description: 'Processes PDF documents',
            license: 'Apache-2.0',
            allowedTools: ['Read', 'Bash'],
            compatibility: 'claude >=3.5',
            metadata: ['author' => 'Symfony', 'version' => '2.0.0'],
        );

        $this->assertSame('pdf-processing', $metadata->getName());
        $this->assertSame('Processes PDF documents', $metadata->getDescription());
        $this->assertSame('Apache-2.0', $metadata->getLicense());
        $this->assertSame(['Read', 'Bash'], $metadata->getAllowedTools());
        $this->assertSame('claude >=3.5', $metadata->getCompatibility());
        $this->assertSame('Symfony', $metadata->getAuthor());
        $this->assertSame('2.0.0', $metadata->getVersion());
    }
}
