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
    /** @var array<string> */
    #[Assert\All(new Assert\Length(max: 255))]
    public array $all;

    #[Assert\AtLeastOneOf([new Assert\EqualTo('a'), new Assert\Type('int')])]
    public string $atLeastOneOf;

    #[Assert\Blank]
    public string $blankString;

    #[Assert\Cidr]
    public string $cidr;

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

    #[Assert\Choice(choices: [2, 3], match: false)]
    public string $choiceInverse;

    #[Assert\Choice(choices: ['a', 'b'])]
    public string $choiceString;

    /** @var array{a: string, b: int} */
    #[Assert\Collection([
        'a' => new Assert\EqualTo('hello'),
        'b' => new Assert\EqualTo(5),
    ])]
    public array $collection;

    /** @var list<mixed> */
    #[Assert\Count(min: 2, max: 4)]
    #[Assert\Unique]
    public array $countedArray = [];

    #[Assert\CssColor(formats: [Assert\CssColor::HEX_SHORT, Assert\CssColor::HEX_LONG])]
    public string $cssColor;

    #[Assert\Date]
    public string $date;

    #[Assert\DateTime]
    public string $dateTime;

    #[Assert\Email]
    public string $email;

    #[Assert\EqualTo('foo')]
    public string $equalTo;

    #[Assert\ExpressionSyntax(allowedVariables: ['foo', 'bar'])]
    public string $expressionSyntax;

    #[Assert\Expression('this.expression != null')]
    public string $expression;

    #[Assert\Hostname]
    public string $hostname;

    #[Assert\Iban]
    public string $iban;

    #[Assert\Ip(version: Assert\Ip::V4)]
    public string $ipv4;

    #[Assert\Ip(version: Assert\Ip::V6)]
    public string $ipv6;

    #[Assert\IsFalse]
    public bool $isFalse;

    #[Assert\IsNull]
    public ?string $isNull = null;

    #[Assert\IsTrue]
    public bool $isTrue;

    #[Assert\Length(min: 2, max: 4)]
    public string $lengthString;

    #[Assert\Json]
    public string $json;

    #[Assert\MacAddress]
    public string $macAddress;

    #[Assert\NotNull]
    public ?string $notNull;

    #[Assert\NegativeOrZero]
    public int $negativeNumber;

    #[Assert\NotBlank]
    public string $notBlankString;

    #[Assert\NotEqualTo('bar')]
    public string $notEqualTo;

    #[Assert\DivisibleBy(3)]
    #[Assert\GreaterThan(10)]
    #[Assert\LessThanOrEqual(100)]
    public int $numberRange;

    #[Assert\Positive]
    public int $positiveNumber;

    #[Assert\Range(min: 5, max: 15)]
    public int $rangedNumber;

    #[Assert\Regex(pattern: '/^[a-z]+$/')]
    public string $regexString;

    #[Assert\Time(withSeconds: false)]
    public string $time;

    #[Assert\Timezone]
    public string $timezone;

    #[Assert\Type(['string', 'null'])]
    public mixed $typedByConstraint;

    #[Assert\Ulid(format: Assert\Ulid::FORMAT_BASE_32)]
    public string $ulid;

    #[Assert\Url]
    public string $url;

    #[Assert\Uuid]
    public string $uuid;

    #[Assert\Week]
    public string $week;

    #[Assert\WordCount(min: 10, max: 20)]
    public string $wordCountBetween;

    #[Assert\WordCount(max: 20)]
    public string $wordCountMaximum;

    #[Assert\WordCount(min: 10)]
    public string $wordCountMinimum;

    #[Assert\Xml]
    public string $xml;

    #[Assert\Yaml]
    public string $yaml;

    /**
     * @return list<int>
     */
    public static function choiceCallback(): array
    {
        return range(1, 3);
    }
}
