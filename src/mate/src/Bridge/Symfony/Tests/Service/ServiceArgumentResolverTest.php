<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Symfony\Tests\Service;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Mate\Bridge\Symfony\Service\ServiceArgumentResolver;
use Symfony\AI\Mate\Bridge\Symfony\Tests\Fixtures\Service\ApiClient;
use Symfony\AI\Mate\Bridge\Symfony\Tests\Fixtures\Service\MessageBus;
use Symfony\AI\Mate\Bridge\Symfony\Tests\Fixtures\Service\Storage;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ServiceArgumentResolverTest extends TestCase
{
    private ServiceArgumentResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new ServiceArgumentResolver();
    }

    public function testNamesArgumentsFromTheConstructorSignature()
    {
        $resolved = $this->resolver->resolve([ApiClient::class, '__construct'], [
            $this->service('http_client'),
            $this->literal('sk-live'),
            $this->literal(30),
            $this->collection([$this->literal('first')]),
            $this->literal(true),
            $this->literal(null),
        ]);

        $this->assertSame(
            ['httpClient', 'apiKey', 'timeoutSeconds', 'scopes', 'debug', 'region'],
            array_column($resolved, 'name'),
        );
    }

    /**
     * @dataProvider sensitiveParameterNameProvider
     */
    public function testRedactsAScalarWhoseParameterNameLooksLikeASecret(string $parameterName)
    {
        $resolved = $this->resolveAgainstSignature([$parameterName], [$this->literal('the-real-value')]);

        $this->assertSame('***REDACTED***', $resolved[0]['value']);
        $this->assertSame($parameterName, $resolved[0]['name']);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function sensitiveParameterNameProvider(): iterable
    {
        yield 'apiKey' => ['apiKey'];
        yield 'password' => ['password'];
        yield 'appSecret' => ['appSecret'];
        yield 'accessToken' => ['accessToken'];
        yield 'bearerValue' => ['bearerValue'];
        yield 'authString' => ['authString'];
        yield 'credentials' => ['credentials'];
        yield 'privateKeyPath' => ['privateKeyPath'];
        yield 'cookieName' => ['cookieName'];
        yield 'uppercase spelling' => ['API_KEY'];
    }

    public function testKeepsAScalarWhoseParameterNameIsHarmless()
    {
        $resolved = $this->resolveAgainstSignature(['timeoutSeconds'], [$this->literal(30)]);

        $this->assertSame(30, $resolved[0]['value']);
    }

    /**
     * A service ID is wiring, not data. Redacting it would remove the only thing worth
     * reading here, and it gives away nothing.
     */
    public function testNeverRedactsServiceReferencesEvenUnderASensitiveParameterName()
    {
        $resolved = $this->resolveAgainstSignature(['secretProvider'], [$this->service('app.vault')]);

        $this->assertSame('app.vault', $resolved[0]['value']);
    }

    public function testKeepsTheServiceIdsOfACollection()
    {
        $resolved = $this->resolver->resolve([MessageBus::class, '__construct'], [
            $this->collection([
                $this->service('messenger.middleware.send_message'),
                $this->service('doctrine.orm.messenger.middleware_factory.transaction'),
            ]),
        ]);

        $this->assertSame('middlewareHandlers', $resolved[0]['name']);
        $this->assertSame('collection', $resolved[0]['type']);
        $this->assertSame(
            ['messenger.middleware.send_message', 'doctrine.orm.messenger.middleware_factory.transaction'],
            array_column($resolved[0]['value'], 'value'),
        );
    }

    public function testRedactsOnlyTheSensitiveEntriesOfAKeyedCollection()
    {
        $resolved = $this->resolver->resolve([Storage::class, '__construct'], [
            $this->collection([
                $this->literal('db.internal', 'host'),
                $this->literal('hunter2', 'password'),
                $this->literal(5432, 'port'),
            ]),
            $this->tagged('app.plugin'),
        ]);

        $this->assertSame(['host', 'password', 'port'], array_column($resolved[0]['value'], 'name'));
        $this->assertSame(['db.internal', '***REDACTED***', 5432], array_column($resolved[0]['value'], 'value'));
    }

    /**
     * A collection sitting under a sensitive parameter has to go entirely, keys or not —
     * otherwise `$credentials = ['user' => ..., 'pass' => ...]` leaks through the entry
     * whose own key looks innocent.
     */
    public function testRedactsEveryLiteralInsideASensitiveCollection()
    {
        $resolved = $this->resolveAgainstSignature(['credentials'], [
            $this->collection([
                $this->literal('admin', 'user'),
                $this->literal('hunter2', 'pass'),
            ]),
        ]);

        $this->assertSame(['***REDACTED***', '***REDACTED***'], array_column($resolved[0]['value'], 'value'));
    }

    public function testNeverRedactsATaggedIterator()
    {
        $resolved = $this->resolveAgainstSignature(['authHandlers'], [$this->tagged('security.authenticator')]);

        $this->assertSame('all services tagged "security.authenticator"', $resolved[0]['value']);
    }

    /**
     * Fail closed: no signature to read means no way to tell a DSN from a timeout, and the
     * safe reading of "we do not know" is "do not show it".
     */
    public function testRedactsScalarsWhenTheClassCannotBeReflected()
    {
        $resolved = $this->resolver->resolve(['App\Absolutely\NotThere', '__construct'], [
            $this->literal('production-database-dsn'),
            $this->service('logger'),
        ]);

        $this->assertSame('#0', $resolved[0]['name']);
        $this->assertSame('***REDACTED***', $resolved[0]['value']);

        // The wiring still shows, because that is the part that was never in danger.
        $this->assertSame('logger', $resolved[1]['value']);
    }

    public function testRedactsScalarsWhenAFactoryLeavesNoClassToReflect()
    {
        $resolved = $this->resolver->resolve(['', 'create'], [$this->literal('production-database-dsn')]);

        $this->assertSame('***REDACTED***', $resolved[0]['value']);
    }

    public function testRedactsScalarsWhenThereIsNoClassAtAll()
    {
        $resolved = $this->resolver->resolve([null, '__construct'], [$this->literal('anything')]);

        $this->assertSame('***REDACTED***', $resolved[0]['value']);
    }

    public function testRedactsScalarsWhenTheFactoryMethodDoesNotExist()
    {
        $resolved = $this->resolver->resolve([ApiClient::class, 'noSuchFactoryMethod'], [$this->literal('anything')]);

        $this->assertSame('***REDACTED***', $resolved[0]['value']);
    }

    /**
     * More arguments than parameters means the extras are unidentified — unless the
     * signature ends in a variadic, which owns all of them.
     */
    public function testRedactsArgumentsBeyondTheEndOfTheSignature()
    {
        $resolved = $this->resolver->resolve([Storage::class, '__construct'], [
            $this->collection([]),
            $this->tagged('app.plugin'),
            $this->literal('unexpected-extra'),
        ]);

        $this->assertSame('#2', $resolved[2]['name']);
        $this->assertSame('***REDACTED***', $resolved[2]['value']);
    }

    public function testNamesVariadicArgumentsAfterTheVariadicParameter()
    {
        $resolved = $this->resolver->resolve([VariadicFixture::class, '__construct'], [
            $this->literal('first'),
            $this->literal('a'),
            $this->literal('b'),
        ]);

        $this->assertSame(['label', 'extras', 'extras'], array_column($resolved, 'name'));
        $this->assertSame(['first', 'a', 'b'], array_column($resolved, 'value'));
    }

    public function testNoArgumentsResolveToNothing()
    {
        $this->assertSame([], $this->resolver->resolve([MessageBus::class, '__construct'], []));
    }

    /**
     * Builds an anonymous class with the given parameter names so a redaction rule can be
     * checked without a fixture class per case.
     *
     * @param list<string>                                                             $parameterNames
     * @param list<array{key: string|null, type: string, value: mixed, literal: bool}> $arguments
     *
     * @return list<array{name: string, type: string, value: mixed}>
     */
    private function resolveAgainstSignature(array $parameterNames, array $arguments): array
    {
        $signature = implode(', ', array_map(static fn (string $name): string => '$'.$name, $parameterNames));
        $class = 'MateArgumentSignature_'.md5($signature);

        if (!class_exists($class, false)) {
            eval(\sprintf('class %s { public function __construct(%s) {} }', $class, $signature));
        }

        return $this->resolver->resolve([$class, '__construct'], $arguments);
    }

    /**
     * @return array{key: string|null, type: string, value: mixed, literal: bool}
     */
    private function service(string $id, ?string $key = null): array
    {
        return ['key' => $key, 'type' => 'service', 'value' => $id, 'literal' => false];
    }

    /**
     * @param list<array{key: string|null, type: string, value: mixed, literal: bool}> $children
     *
     * @return array{key: string|null, type: string, value: mixed, literal: bool}
     */
    private function collection(array $children, ?string $key = null): array
    {
        return ['key' => $key, 'type' => 'collection', 'value' => $children, 'literal' => false];
    }

    /**
     * @return array{key: string|null, type: string, value: mixed, literal: bool}
     */
    private function literal(mixed $value, ?string $key = null): array
    {
        return ['key' => $key, 'type' => 'scalar', 'value' => $value, 'literal' => true];
    }

    /**
     * @return array{key: string|null, type: string, value: mixed, literal: bool}
     */
    private function tagged(string $tag, ?string $key = null): array
    {
        return ['key' => $key, 'type' => 'scalar', 'value' => \sprintf('all services tagged "%s"', $tag), 'literal' => false];
    }
}

/**
 * A variadic signature cannot be written with promoted properties, so it keeps a body.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class VariadicFixture
{
    /**
     * @var list<string>
     */
    public readonly array $arguments;

    public function __construct(string $label = '', string ...$extras)
    {
        $this->arguments = [$label, ...$extras];
    }
}
