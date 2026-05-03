Runtime-driven Tool Parameters and Structured Output
====================================================

This guide shows how to constrain tool parameters or structured output DTO properties
with values that are only known at runtime — environment variables, database lookups,
or any injected service. The same mechanism applies to tools and structured output
because it lives in the JSON Schema describer chain.

Prerequisites

* Symfony AI Platform component
* Symfony AI Agent component (for the tool example)
* Symfony AI Bundle (recommended, for autoconfiguration)

The ``#[Schema(enum: [...])]`` attribute is convenient for static allowlists, but PHP
attributes only accept constant expressions. As soon as the allowed values come from
``.env``, a database table, or any service, the attribute is no longer usable. The
:class:`Symfony\\AI\\Platform\\Contract\\JsonSchema\\Attribute\\SchemaSource` attribute
fills that gap by referencing a service implementing
:class:`Symfony\\AI\\Platform\\Contract\\JsonSchema\\Provider\\SchemaProviderInterface`,
which contributes a JSON Schema fragment computed at runtime.

Setting up a Schema Provider
----------------------------

First, create a provider that returns the runtime-computed fragment. In this example,
the allowed statuses come from an environment variable::

    namespace App\Schema;

    use Symfony\AI\Platform\Contract\JsonSchema\Provider\SchemaProviderInterface;
    use Symfony\Component\DependencyInjection\Attribute\Autowire;

    final class PartStatusProvider implements SchemaProviderInterface
    {
        public function __construct(
            #[Autowire('%env(csv:ACME_PART_STATUSES)%')]
            private readonly array $statuses,
        ) {
        }

        public function getSchemaFragment(array $context = []): array
        {
            return ['enum' => $this->statuses];
        }
    }

A second provider can pull values from any other service — here, a database-backed
catalog::

    namespace App\Schema;

    use Symfony\AI\Platform\Contract\JsonSchema\Provider\SchemaProviderInterface;

    final class PartColorProvider implements SchemaProviderInterface
    {
        public function __construct(private readonly PartColorCatalog $catalog)
        {
        }

        public function getSchemaFragment(array $context = []): array
        {
            return ['enum' => $this->catalog->labels()];
        }
    }

The AI Bundle autoconfigures every class implementing ``SchemaProviderInterface``, so
declaring them as services is enough — no tag, no compiler pass.

``#[SchemaSource]`` accepts any container service ID, not only fully-qualified class
names. This lets you register the same provider class as multiple services with
different configurations and reference each one by its service ID:

.. code-block:: yaml

    # config/services.yaml
    services:
        app.provider.status:
            class: App\Schema\EnumSchemaProvider
            arguments:
                $values: ['draft', 'published', 'archived']

        app.provider.priority:
            class: App\Schema\EnumSchemaProvider
            arguments:
                $values: ['low', 'medium', 'high']

::

    #[SchemaSource('app.provider.status')]
    string $status,
    #[SchemaSource('app.provider.priority')]
    string $priority,

Case 1: Tool Parameters
-----------------------

Reference each provider from a tool parameter via ``#[SchemaSource]``::

    use App\Schema\PartColorProvider;
    use App\Schema\PartStatusProvider;
    use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
    use Symfony\AI\Platform\Contract\JsonSchema\Attribute\SchemaSource;

    #[AsTool('search_parts', 'Search parts by status and color')]
    final class SearchPartsTool
    {
        public function __invoke(
            #[SchemaSource(PartStatusProvider::class)]
            string $status,
            #[SchemaSource(PartColorProvider::class)]
            string $color,
        ): array {
            // ...
        }
    }

The LLM now sees the runtime-resolved enums in the tool's JSON Schema and is constrained
accordingly when calling ``search_parts``.

Case 2: Structured Output Properties
------------------------------------

The same attribute works on properties of a DTO used as ``response_format``::

    use App\Schema\PartColorProvider;
    use App\Schema\PartStatusProvider;
    use Symfony\AI\Platform\Contract\JsonSchema\Attribute\SchemaSource;

    final class PartQuery
    {
        public function __construct(
            #[SchemaSource(PartStatusProvider::class)]
            public readonly string $status,
            #[SchemaSource(PartColorProvider::class)]
            public readonly string $color,
        ) {
        }
    }

A single provider implementation serves both tools and structured output without
duplication.

Composing with Static Constraints
---------------------------------

Fragments are merged with ``array_replace_recursive`` on top of the schema built from
reflection, ``#[Schema]``, PHPDoc and Validator constraints, so static and dynamic
concerns coexist on the same parameter::

    public function __invoke(
        #[SchemaSource(PartStatusProvider::class)]
        #[Schema(description: 'The current part status')]
        string $status,
    ): array {
        // schema['properties']['status'] = [
        //     'type' => 'string',
        //     'description' => 'The current part status',
        //     'enum' => ['active', 'archived', ...],
        // ]
    }

Passing Context to Providers
----------------------------

Providers can be made generic by accepting a context array from the attribute. This
is useful to reuse the same provider class for different data sets::

    namespace App\Schema;

    use Doctrine\ORM\EntityManagerInterface;
    use Symfony\AI\Platform\Contract\JsonSchema\Provider\SchemaProviderInterface;

    final class EntityEnumProvider implements SchemaProviderInterface
    {
        public function __construct(private readonly EntityManagerInterface $em)
        {
        }

        public function getSchemaFragment(array $context = []): array
        {
            $entity = $context['entity'] ?? throw new \LogicException('Missing entity context.');
            $field = $context['field'] ?? 'name';

            $values = $this->em->getRepository($entity)->findAll();

            return [
                'type' => 'string',
                'enum' => array_map(fn($obj) => $obj->{'get'.ucfirst($field)}(), $values),
            ];
        }
    }

Pass the context as the second argument to ``#[SchemaSource]``::

    public function __invoke(
        #[SchemaSource(EntityEnumProvider::class, ['entity' => Color::class])]
        string $color,
        #[SchemaSource(EntityEnumProvider::class, ['entity' => Category::class, 'field' => 'label'])]
        string $category,
    ): array {
        // ...
    }

Multiple ``#[SchemaSource]`` attributes can also be stacked on the same parameter; they
are applied in declaration order, so a later provider's keys override an earlier one's.

Standalone Wiring (without the AI Bundle)
-----------------------------------------

When using the components directly, build a ``Describer`` chain that includes
:class:`Symfony\\AI\\Platform\\Contract\\JsonSchema\\Describer\\SchemaSourceDescriber`
and provide it with a PSR-11 container exposing your providers, then pass the resulting
:class:`Symfony\\AI\\Platform\\Contract\\JsonSchema\\Factory` to
:class:`Symfony\\AI\\Agent\\Toolbox\\ToolFactory\\ReflectionToolFactory` for tool
parameters or to :class:`Symfony\\AI\\Platform\\StructuredOutput\\ResponseFormatFactory`
for structured output. See ``examples/toolbox/schema-source.php`` and
``examples/openai/structured-output-schema-source.php`` in the repository for complete
runnable setups.

.. note::

    Tool metadata is cached on the :class:`Symfony\\AI\\Agent\\Toolbox\\Toolbox` instance
    on first call to ``getTools()``. In a typical PHP-FPM request the toolbox is recreated
    each time, so providers are re-invoked per request. In a long-running process (worker,
    daemon) the cached schema lives as long as the toolbox instance, so changes to the
    underlying values are not picked up until the worker restarts.

.. note::

    The describer does not validate the shape of the fragment returned by
    ``getSchemaFragment()``. Returning a malformed JSON Schema produces a malformed
    schema sent to the LLM — stick to documented JSON Schema keys.

.. note::

    ``#[SchemaSource]`` only accepts a ``class-string<SchemaProviderInterface>``. Service
    IDs as strings are not supported, which guarantees refactor-safe references.