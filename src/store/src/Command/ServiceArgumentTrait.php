<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Command;

use Symfony\AI\Store\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 *
 * @internal
 */
trait ServiceArgumentTrait
{
    /**
     * Asks for the service in case the argument is missing or does not match any of the given names.
     *
     * @param array<int|string> $names
     */
    private function interactForService(InputInterface $input, OutputInterface $output, string $argument, string $label, array $names): void
    {
        $names = array_map(strval(...), $names);
        if ([] === $names) {
            return;
        }

        $value = $input->getArgument($argument);
        if (null !== $value && \in_array($value, $names, true)) {
            return;
        }

        $io = new SymfonyStyle($input, $output);
        $alternatives = [];
        if (null !== $value) {
            $io->warning(\sprintf('The "%s" %s does not exist.', $value, $label));
            $alternatives = $this->findServiceAlternatives($value, $names);
        }

        $choices = array_values(array_unique([...$alternatives, ...$names]));
        $question = new ChoiceQuestion(\sprintf('Which %s do you want to use?', $label), $choices, $choices[0]);

        $input->setArgument($argument, $io->askQuestion($question));
    }

    /**
     * @param array<int|string> $names
     */
    private function resolveServiceName(string $value, string $label, array $names): string
    {
        $names = array_map(strval(...), $names);
        if (\in_array($value, $names, true)) {
            return $value;
        }

        if ([] !== $alternatives = $this->findServiceAlternatives($value, $names)) {
            throw new RuntimeException(\sprintf('The "%s" '.$label.' does not exist, did you mean "%s"?', $value, implode('", "', $alternatives)));
        }

        if ([] === $names) {
            throw new RuntimeException(\sprintf('The "%s" '.$label.' does not exist.', $value));
        }

        throw new RuntimeException(\sprintf('The "%s" '.$label.' does not exist, use "%s".', $value, implode('", "', $names)));
    }

    /**
     * @param string[] $names
     *
     * @return string[]
     */
    private function findServiceAlternatives(string $value, array $names): array
    {
        $segments = explode('.', $value);
        $alternatives = [];
        foreach ($names as $name) {
            $distance = \PHP_INT_MAX;
            foreach (array_keys($segments) as $offset) {
                $distance = min($distance, levenshtein(implode('.', \array_slice($segments, $offset)), $name));
            }

            if ($distance <= max(2, intdiv(\strlen($name), 3)) || str_contains($name, $value)) {
                $alternatives[$name] = $distance;
            }
        }

        asort($alternatives);

        return array_keys($alternatives);
    }
}
