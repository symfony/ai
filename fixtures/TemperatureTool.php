<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Fixtures;

use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
#[AsTool('get_temperature', 'Returns the current temperature in degrees Celsius for a city.')]
final class TemperatureTool
{
    /**
     * Records every city the tool was invoked with, to make parallel tool calls observable.
     *
     * @var list<string>
     */
    public array $calledFor = [];

    public function __invoke(string $city): string
    {
        $this->calledFor[] = $city;

        return match ($city) {
            'Berlin' => '12°C',
            'Paris' => '15°C',
            'Rome' => '19°C',
            default => 'unknown',
        };
    }
}
