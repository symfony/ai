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

use AsyncAws\Core\AbstractApi;
use AsyncAws\Core\AwsError\AwsErrorFactoryInterface;
use AsyncAws\Core\AwsError\JsonRestAwsErrorFactory;
use AsyncAws\Core\Configuration;
use AsyncAws\Core\RequestContext;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class BedrockClient extends AbstractApi
{
    /**
     * @see https://docs.aws.amazon.com/bedrock/latest/APIReference/API_ListFoundationModels.html
     *
     * @return list<array{modelId: string, modelName: string, providerName: string, inputModalities: list<string>, outputModalities: list<string>}>
     */
    public function listFoundationModels(?string $provider = null, ?string $outputModality = null, ?string $inferenceType = null): array
    {
        $input = new ListFoundationModelsRequest([
            'byProvider' => $provider,
            'byOutputModality' => $outputModality,
            'byInferenceType' => $inferenceType,
        ]);

        $response = $this->getResponse($input->request(), new RequestContext(['operation' => 'ListFoundationModels', 'region' => $input->getRegion()]));

        $data = $response->toArray();

        return $data['modelSummaries'] ?? [];
    }

    protected function getAwsErrorFactory(): AwsErrorFactoryInterface
    {
        return new JsonRestAwsErrorFactory();
    }

    protected function getEndpointMetadata(?string $region): array
    {
        if (null === $region) {
            $region = Configuration::DEFAULT_REGION;
        }

        return [
            'endpoint' => "https://bedrock.$region.amazonaws.com",
            'signRegion' => $region,
            'signService' => 'bedrock',
            'signVersions' => ['v4'],
        ];
    }
}
