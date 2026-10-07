<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\StructuredOutput;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Event\InvocationEvent;
use Symfony\AI\Platform\Event\ResultEvent;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\MissingModelSupportException;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class PlatformSubscriber implements EventSubscriberInterface
{
    public const RESPONSE_FORMAT = 'response_format';
    public const MISSING_PROPERTIES_ONLY = 'missing_properties_only';

    private ?string $outputType = null;

    private ?object $objectToPopulate = null;

    private bool $missingPropertiesOnly = false;

    private SerializerInterface&DenormalizerInterface $serializer;

    public function __construct(
        private readonly ResponseFormatFactoryInterface $responseFormatFactory = new ResponseFormatFactory(),
        (SerializerInterface&DenormalizerInterface)|null $serializer = null,
        private readonly InstanceSchemaFilter $instanceSchemaFilter = new InstanceSchemaFilter(),
    ) {
        $this->serializer = $serializer ?? new Serializer();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            InvocationEvent::class => 'processInput',
            ResultEvent::class => 'processResult',
        ];
    }

    /**
     * @throws MissingModelSupportException When structured output is requested but the model doesn't support it
     */
    public function processInput(InvocationEvent $event): void
    {
        $this->reset();

        $options = $event->getOptions();

        // The option is consumed here and must never reach the provider
        if (\array_key_exists(self::MISSING_PROPERTIES_ONLY, $options)) {
            $this->missingPropertiesOnly = (bool) $options[self::MISSING_PROPERTIES_ONLY];
            unset($options[self::MISSING_PROPERTIES_ONLY]);
            $event->setOptions($options);
        }

        if ($this->missingPropertiesOnly && !\is_object($options[self::RESPONSE_FORMAT] ?? null)) {
            throw new InvalidArgumentException(\sprintf('The "%s" option requires the "%s" option to be the instance to populate.', self::MISSING_PROPERTIES_ONLY, self::RESPONSE_FORMAT));
        }

        if (!isset($options[self::RESPONSE_FORMAT])) {
            return;
        }

        $responseFormat = $options[self::RESPONSE_FORMAT];

        if (\is_object($responseFormat)) {
            $this->objectToPopulate = $responseFormat;
            $className = $responseFormat::class;
        } elseif (\is_string($responseFormat)) {
            if (class_exists($responseFormat)) {
                $className = $responseFormat;
            } elseif (str_contains($responseFormat, '\\')) {
                throw new InvalidArgumentException(\sprintf('The response format class "%s" does not exist.', $responseFormat));
            } else {
                return;
            }
        } else {
            return;
        }

        if (!$event->getModel()->supports(Capability::OUTPUT_STRUCTURED)) {
            throw MissingModelSupportException::forStructuredOutput($event->getModel());
        }

        $this->outputType = $className;

        $options[self::RESPONSE_FORMAT] = $this->responseFormatFactory->create($className);

        if ($this->missingPropertiesOnly && null !== $this->objectToPopulate) {
            $options[self::RESPONSE_FORMAT]['json_schema']['schema'] = $this->instanceSchemaFilter->filter($options[self::RESPONSE_FORMAT]['json_schema']['schema'], $this->objectToPopulate);
        }

        $event->setOptions($options);
    }

    public function processResult(ResultEvent $event): void
    {
        $options = $event->getOptions();

        if (!isset($options[self::RESPONSE_FORMAT])) {
            return;
        }

        $deferred = $event->getDeferredResult();
        $converter = new ResultConverter(
            $deferred->getResultConverter(),
            $this->serializer,
            $this->outputType,
            $this->objectToPopulate,
        );

        $event->setDeferredResult(new DeferredResult($converter, $deferred->getRawResult(), $options));

        $this->reset();
    }

    private function reset(): void
    {
        $this->outputType = null;
        $this->objectToPopulate = null;
        $this->missingPropertiesOnly = false;
    }
}
