Using Object Instances with Structured Output
=============================================

Pass object instances to ``response_format`` to populate missing fields while preserving
existing values. Use ``template_vars`` to provide object context to the AI model.

Basic Example
-------------

.. code-block:: php

    use Symfony\AI\Platform\Message\Message;
    use Symfony\AI\Platform\Message\MessageBag;
    use Symfony\AI\Platform\Message\Template;

    $city = new City(name: 'Berlin');

    $messages = new MessageBag(
        Message::ofUser(Template::string('Research missing data for: {city.name}'))
    );

    $result = $platform->invoke($model, $messages, [
        'template_vars' => ['city' => $city],
        'response_format' => $city,
    ]);

    // Same instance returned with populated fields
    assert($city === $result->asObject());

Use Cases
---------

**Enriching database records**
    Populate missing fields from partial database data.

**Updating incomplete records**
    Generate AI content for null fields in existing objects.

**Progressive data collection**
    Build data incrementally across multiple AI calls using the same object instance.

Configuration
-------------

Register ``TemplateRendererListener`` with a normalizer::

    use Symfony\AI\Platform\EventListener\TemplateRendererListener;
    use Symfony\AI\Platform\Message\TemplateRenderer\StringTemplateRenderer;
    use Symfony\AI\Platform\Message\TemplateRenderer\TemplateRendererRegistry;
    use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

    $normalizer = new ObjectNormalizer();
    $registry = new TemplateRendererRegistry([new StringTemplateRenderer()]);
    $dispatcher->addSubscriber(new TemplateRendererListener($registry, $normalizer));
    $dispatcher->addSubscriber(new PlatformSubscriber());

Normalization Options
---------------------

Control object normalization with ``template_options['normalizer_context']``::

    $result = $platform->invoke($model, $messages, [
        'template_vars' => ['product' => $product],
        'template_options' => [
            'normalizer_context' => [
                'groups' => ['public'],
            ],
        ],
        'response_format' => $product,
    ]);

Related Documentation
---------------------

* :doc:`../components/platform` - Platform component documentation
