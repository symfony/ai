<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Bedrock;

use AsyncAws\Core\Input;
use AsyncAws\Core\Request;
use AsyncAws\Core\Stream\StreamFactory;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ListFoundationModelsRequest extends Input
{
    private ?string $byProvider;
    private ?string $byOutputModality;
    private ?string $byInferenceType;

    /**
     * @param array{
     *   byProvider?: ?string,
     *   byOutputModality?: ?string,
     *   byInferenceType?: ?string,
     *   '@region'?: ?string,
     * } $input
     */
    public function __construct(array $input = [])
    {
        $this->byProvider = $input['byProvider'] ?? null;
        $this->byOutputModality = $input['byOutputModality'] ?? null;
        $this->byInferenceType = $input['byInferenceType'] ?? null;
        parent::__construct($input);
    }

    public function request(): Request
    {
        $query = [];
        if (null !== $this->byProvider) {
            $query['byProvider'] = $this->byProvider;
        }
        if (null !== $this->byOutputModality) {
            $query['byOutputModality'] = $this->byOutputModality;
        }
        if (null !== $this->byInferenceType) {
            $query['byInferenceType'] = $this->byInferenceType;
        }

        return new Request('GET', '/foundation-models', $query, ['Accept' => 'application/json'], StreamFactory::create(''));
    }
}
