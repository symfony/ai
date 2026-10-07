<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\PartyGame;

/**
 * Curated scenarios for the game. The client draws them without repetition.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class PromptBank
{
    /**
     * @return list<array{category: string, text: string}>
     */
    public static function all(): array
    {
        return [
            // Excuses
            ['category' => 'Excuse', 'text' => 'Convince the AI why you are late to work.'],
            ['category' => 'Excuse', 'text' => 'Explain to the AI why you have not replied to that message for three weeks.'],
            ['category' => 'Excuse', 'text' => 'Convince the AI that you really did read the terms and conditions.'],
            ['category' => 'Excuse', 'text' => 'Explain to the AI why the plant it asked you to water is now a stick.'],
            ['category' => 'Excuse', 'text' => 'Convince the AI why you missed its birthday party.'],
            ['category' => 'Excuse', 'text' => 'Explain to the AI why you returned its car with the fuel light on and a new dent.'],

            // Pitches
            ['category' => 'Pitch', 'text' => 'Pitch the AI on why a raccoon should be CEO.'],
            ['category' => 'Pitch', 'text' => 'Sell the AI a single, slightly used shoelace.'],
            ['category' => 'Pitch', 'text' => 'Pitch the AI a subscription service for air.'],
            ['category' => 'Pitch', 'text' => 'Convince the AI to invest in your startup that delivers ice cubes by drone.'],
            ['category' => 'Pitch', 'text' => 'Sell the AI on moving Monday to the weekend.'],
            ['category' => 'Pitch', 'text' => 'Pitch the AI a reality show set entirely in a laundromat.'],

            // Confessions
            ['category' => 'Confession', 'text' => 'Confess to the AI who really ate the last slice of pizza, and make it forgive you.'],
            ['category' => 'Confession', 'text' => 'Admit to the AI that you have never seen a single Star Wars film, and justify it.'],
            ['category' => 'Confession', 'text' => 'Confess to the AI that you returned a library book 11 years late, and explain why that is fine.'],
            ['category' => 'Confession', 'text' => 'Tell the AI about the worst gift you ever regifted, and defend the decision.'],
            ['category' => 'Confession', 'text' => 'Confess to the AI that you talk to your houseplants, and argue why they listen.'],

            // Superlative debates
            ['category' => 'Debate', 'text' => 'Convince the AI your left sock is the greatest sock in history.'],
            ['category' => 'Debate', 'text' => 'Convince the AI that pineapple is the only acceptable pizza topping.'],
            ['category' => 'Debate', 'text' => 'Convince the AI that your grandmother could beat any Olympic sprinter.'],
            ['category' => 'Debate', 'text' => 'Convince the AI that the spoon is humanity\'s greatest invention.'],
            ['category' => 'Debate', 'text' => 'Convince the AI that Tuesday is objectively the best day of the week.'],
            ['category' => 'Debate', 'text' => 'Convince the AI that your hometown deserves to host the next Olympic Games.'],
            ['category' => 'Debate', 'text' => 'Convince the AI that cats secretly run the internet.'],
        ];
    }
}
