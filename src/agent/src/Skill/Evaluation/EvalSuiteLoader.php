<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Skill\Evaluation;

use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Loads evaluation suites from evals/evals.json within a skill directory.
 *
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class EvalSuiteLoader implements EvalSuiteLoaderInterface
{
    public function __construct(
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
    }

    public function load(string $skillDirectory): EvalSuite
    {
        $evalsFile = rtrim($skillDirectory, '/').'/evals/evals.json';

        if (!$this->filesystem->exists($evalsFile)) {
            throw new InvalidArgumentException(\sprintf('Eval file not found at "%s".', $evalsFile));
        }

        $content = $this->filesystem->readFile($evalsFile);
        $data = json_decode($content, true);

        if (!\is_array($data)) {
            throw new InvalidArgumentException(\sprintf('Unable to parse JSON in "%s".', $evalsFile));
        }

        if (!isset($data['skill_name']) || !\is_string($data['skill_name'])) {
            throw new InvalidArgumentException(\sprintf('Missing or invalid "skill_name" in "%s".', $evalsFile));
        }

        if (!isset($data['evals']) || !\is_array($data['evals'])) {
            throw new InvalidArgumentException(\sprintf('Missing or invalid "evals" array in "%s".', $evalsFile));
        }

        $evals = [];
        foreach ($data['evals'] as $index => $evalData) {
            if (!\is_array($evalData)) {
                throw new InvalidArgumentException(\sprintf('Invalid eval entry at index %d in "%s".', $index, $evalsFile));
            }

            if (!isset($evalData['id']) || !\is_int($evalData['id'])) {
                throw new InvalidArgumentException(\sprintf('Missing or invalid "id" at eval index %d in "%s".', $index, $evalsFile));
            }

            if (!isset($evalData['prompt']) || !\is_string($evalData['prompt'])) {
                throw new InvalidArgumentException(\sprintf('Missing or invalid "prompt" at eval index %d in "%s".', $index, $evalsFile));
            }

            if (!isset($evalData['expected_output']) || !\is_string($evalData['expected_output'])) {
                throw new InvalidArgumentException(\sprintf('Missing or invalid "expected_output" at eval index %d in "%s".', $index, $evalsFile));
            }

            $files = [];
            if (isset($evalData['files']) && \is_array($evalData['files'])) {
                $files = $evalData['files'];
            }

            $assertions = [];
            if (isset($evalData['assertions']) && \is_array($evalData['assertions'])) {
                $assertions = $evalData['assertions'];
            }

            $evals[] = new EvalCase($evalData['id'], $evalData['prompt'], $evalData['expected_output'], $files, $assertions);
        }

        return new EvalSuite($data['skill_name'], $evals);
    }
}
