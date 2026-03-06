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

final class ValidatorConstraintsIntlFixture
{
    #[Assert\Country]
    public string $countryAlpha2;

    #[Assert\Country(alpha3: true)]
    public string $countryAlpha3;

    #[Assert\Language]
    public string $languageAlpha2;

    #[Assert\Language(alpha3: true)]
    public string $languageAlpha3;

    #[Assert\Locale]
    public string $locale;

    #[Assert\Currency]
    public string $currency;
}
