<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Bedrock\Mantle;

use AsyncAws\Core\Configuration;
use AsyncAws\Core\Credentials\CredentialProvider;
use AsyncAws\Core\Credentials\Credentials;
use AsyncAws\Core\Request;
use AsyncAws\Core\RequestContext;
use AsyncAws\Core\Signer\SignerV4;
use AsyncAws\Core\Stream\StringStream;
use Symfony\AI\Platform\Exception\RuntimeException;

/**
 * @internal
 *
 * @author asrar <aszenz@gmail.com>
 */
final class SigV4RequestSigner
{
    private const SERVICE = 'bedrock';

    public function __construct(
        private readonly string $region,
        private readonly CredentialProvider $credentialProvider,
    ) {
    }

    /**
     * @param array<string, string> $headers
     *
     * @return array<string, string>
     */
    public function sign(string $url, string $path, string $body, array $headers = ['content-type' => 'application/json']): array
    {
        $credentials = $this->credentialProvider->getCredentials(Configuration::create(['region' => $this->region]));
        if (!$credentials instanceof Credentials) {
            throw new RuntimeException('Unable to resolve AWS credentials for Bedrock Mantle SigV4 authentication.');
        }

        $request = new Request('POST', $path, [], $headers, StringStream::create($body));
        $request->setEndpoint($url);

        (new SignerV4(self::SERVICE, $this->region))->sign($request, $credentials, new RequestContext());

        return $request->getHeaders();
    }
}
