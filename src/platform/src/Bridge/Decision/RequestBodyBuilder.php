<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Decision;

use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;

/**
 * Builds the JSON body of a "POST /v1/systemone" request from an {@see Evaluation} payload.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 *
 * @internal
 */
final class RequestBodyBuilder
{
    /**
     * @param array<mixed>|string $payload
     *
     * @return array{model: string, state: mixed, questions: object, images?: list<string>}
     */
    public static function build(Model $model, array|string $payload, bool $supportsImages = false): array
    {
        if (!\is_array($payload) || !isset($payload['state'], $payload['questions']) || !\is_array($payload['questions'])) {
            throw new InvalidArgumentException('Decision payload must be an array with a "state" key and a "questions" map.');
        }

        $questions = [];
        foreach ($payload['questions'] as $id => $question) {
            if (!\is_array($question)) {
                throw new InvalidArgumentException(\sprintf('Question "%s" must be an array, "%s" given.', $id, get_debug_type($question)));
            }

            if (isset($question['criteria']) && 'score' !== ($question['type'] ?? null)) {
                // The criteria of noul and choice questions are maps, which must remain JSON objects even with numeric keys
                $question['criteria'] = (object) $question['criteria'];
            }

            $questions[$id] = $question;
        }

        $body = [
            'model' => $model->getName(),
            'state' => $payload['state'],
            'questions' => (object) $questions,
        ];

        if (isset($payload['images']) && [] !== $payload['images']) {
            if (!$supportsImages) {
                throw new InvalidArgumentException(\sprintf('The decision model "%s" does not support image inputs.', $model->getName()));
            }
            if (!\is_array($payload['images']) || !array_is_list($payload['images'])) {
                throw new InvalidArgumentException('Decision images must be a list of base64-encoded strings.');
            }
            foreach ($payload['images'] as $image) {
                if (!\is_string($image) || '' === $image) {
                    throw new InvalidArgumentException('Decision images must be a list of base64-encoded strings.');
                }
            }
            $body['images'] = $payload['images'];
        }

        return $body;
    }
}
