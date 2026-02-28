<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Fixtures\StructuredOutput;

use Symfony\Component\Validator\Constraints as Assert;

final class ValidatorConstraintsFixture
{
    #[Assert\NotBlank]
    public string $notBlankString;

    #[Assert\Blank]
    public string $blankString;

    #[Assert\Length(min: 2, max: 4)]
    public string $lengthString;

    #[Assert\Regex(pattern: '/^[a-z]+$/')]
    public string $regexString;

    #[Assert\Choice(choices: ['a', 'b'])]
    public string $choiceString;

    /**
     * @var list<string>
     */
    #[Assert\Choice(choices: ['x', 'y'], multiple: true, min: 1, max: 2)]
    public array $choiceArray = [];

    /**
     * @var list<int>
     */
    #[Assert\Choice(callback: 'choiceCallback')]
    public array $choiceCallback;

    /**
     * @var list<mixed>
     */
    #[Assert\Count(min: 2, max: 4)]
    #[Assert\Unique]
    public array $countedArray = [];

    #[Assert\DivisibleBy(3)]
    #[Assert\GreaterThan(10)]
    #[Assert\LessThanOrEqual(100)]
    public int $numberRange;

    #[Assert\Range(min: 5, max: 15)]
    public int $rangedNumber;

    #[Assert\Positive]
    public int $positiveNumber;

    #[Assert\NegativeOrZero]
    public int $negativeNumber;

    #[Assert\EqualTo('foo')]
    public string $equalTo;

    #[Assert\NotEqualTo('bar')]
    public string $notEqualTo;

    #[Assert\Email]
    public string $email;

    #[Assert\Url]
    public string $url;

    #[Assert\Date]
    public string $date;

    #[Assert\DateTime]
    public string $dateTime;

    #[Assert\Time(withSeconds: false)]
    public string $time;

    #[Assert\Ip(version: Assert\Ip::V4)]
    public string $ipv4;

    #[Assert\Ip(version: Assert\Ip::V6)]
    public string $ipv6;

    #[Assert\Hostname]
    public string $hostname;

    #[Assert\Uuid]
    public string $uuid;

    #[Assert\Ulid(format: Assert\Ulid::FORMAT_BASE_32)]
    public string $ulid;

    #[Assert\IsTrue]
    public bool $mustBeTrue;

    #[Assert\IsFalse]
    public bool $mustBeFalse;

    #[Assert\IsNull]
    public ?string $mustBeNull = null;

    #[Assert\NotNull]
    public ?string $mustNotBeNull;

    #[Assert\Type(['string', 'null'])]
    public mixed $typedByConstraint;

    /**
     * @return list<int>
     */
    public static function choiceCallback(): array
    {
        return range(1, 3);
    }
}
