.. card:
    title: Runtime-driven Tool Parameters
    description: Constrain tool parameters and structured output with values from env, a database, or services.
    icon: list-check
    components: Agent

Runtime-driven Tool Parameters and Structured Output
====================================================

The ``#[Schema(enum: [...])]`` attribute constrains a value to a static allowlist, but PHP
attributes only accept constant expressions. As soon as the allowed values come from ``.env``,
a database table, or any service, ``enum`` alone is no longer usable. In this guide you will
build a *schema provider* that contributes a JSON Schema fragment at runtime and reference it
from both a tool parameter and a structured output DTO.

Prerequisites
-------------

* Symfony AI Platform component
* Symfony AI Agent component (for the tool example)
* Symfony AI Bundle (recommended, for autoconfiguration)

Step 1: Install Packages
------------------------

.. code-block:: terminal

    $ composer require symfony/ai-platform symfony/ai-agent

Step 2: Create a Schema Provider
--------------------------------

A provider implements :class:`Symfony\\AI\\Platform\\Contract\\JsonSchema\\Provider\\SchemaProviderInterface`
and returns the runtime-computed fragment from ``getSchemaFragment()``. Here the allowed statuses
come from an environment variable::

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

A second provider can pull values from any other service — here, a database-backed catalog::

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

The AI Bundle autoconfigures every class implementing ``SchemaProviderInterface``, so declaring
them as services is enough — no tag, no compiler pass.

Step 3: Constrain a Tool Parameter
----------------------------------

Reference each provider from a tool parameter via ``#[Schema(provider: ...)]``::

    namespace App\Tool;

    use App\Schema\PartColorProvider;
    use App\Schema\PartStatusProvider;
    use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
    use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;

    #[AsTool('search_parts', 'Search parts by status and color')]
    final class SearchPartsTool
    {
        public function __invoke(
            #[Schema(provider: PartStatusProvider::class)]
            string $status,
            #[Schema(provider: PartColorProvider::class)]
            string $color,
        ): array {
            // ...
        }
    }

The LLM now sees the runtime-resolved enums in the tool's JSON Schema and is constrained
accordingly when calling ``search_parts``.

.. tip::

    On tagged tools the AI Bundle validates every ``provider`` reference at container build
    time, so a typo or a missing service fails the build instead of a request.

Step 4: Reuse the Provider for Structured Output
------------------------------------------------

The same attribute works on properties of a DTO used as ``response_format``, so a single
provider implementation serves both tools and structured output without duplication::

    namespace App\Dto;

    use App\Schema\PartColorProvider;
    use App\Schema\PartStatusProvider;
    use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;

    final class PartQuery
    {
        public function __construct(
            #[Schema(provider: PartStatusProvider::class)]
            public readonly string $status,
            #[Schema(provider: PartColorProvider::class)]
            public readonly string $color,
        ) {
        }
    }

Step 5: Compose with Static Constraints
---------------------------------------

The runtime fragment is merged on top of the static schema built from the same attribute,
reflection, PHPDoc and Validator constraints, so static and dynamic concerns coexist on the
same parameter::

    public function __invoke(
        #[Schema(provider: PartStatusProvider::class, description: 'The current part status')]
        string $status,
    ): array {
        // schema['properties']['status'] = [
        //     'type' => 'string',
        //     'description' => 'The current part status',
        //     'enum' => ['active', 'archived', ...],
        // ]
    }

When the provider returns a key that is already present in the static schema, the provider's
value replaces it.

Step 6: Pass Context to a Provider
----------------------------------

Providers can be made generic by accepting a context array from the attribute, which lets you
reuse the same provider class for different data sets::

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
                'enum' => array_map(fn ($obj) => $obj->{'get'.ucfirst($field)}(), $values),
            ];
        }
    }

Pass the context via the ``context`` argument of ``#[Schema]``::

    public function __invoke(
        #[Schema(provider: EntityEnumProvider::class, context: ['entity' => Color::class])]
        string $color,
        #[Schema(provider: EntityEnumProvider::class, context: ['entity' => Category::class, 'field' => 'label'])]
        string $category,
    ): array {
        // ...
    }

Step 7: Register Multiple Instances by Service ID
-------------------------------------------------

The ``provider`` argument accepts any container service ID, not only fully-qualified class
names. This lets you register the same provider class as several services with different
configurations and reference each one by its service ID:

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

    #[Schema(provider: 'app.provider.status')]
    string $status,
    #[Schema(provider: 'app.provider.priority')]
    string $priority,

Using the Components Directly
-----------------------------

Without the AI Bundle, create a
:class:`Symfony\\AI\\Platform\\Contract\\JsonSchema\\Enricher\\SchemaAttributeEnricher` and pass
it an iterable of providers indexed by the identifier referenced from ``#[Schema(provider: ...)]``
(FQCN by default). Pass it to the :class:`Symfony\\AI\\Platform\\Contract\\JsonSchema\\Factory`, which is then handed
to :class:`Symfony\\AI\\Agent\\Toolbox\\ToolFactory\\ReflectionToolFactory` for tool parameters or
to :class:`Symfony\\AI\\Platform\\StructuredOutput\\ResponseFormatFactory` for structured output.
See ``examples/toolbox/schema-provider.php`` and
``examples/openai/structured-output-schema-provider.php`` in the repository for complete runnable
setups.

Caveats
-------

.. note::

    Tool metadata is cached on the :class:`Symfony\\AI\\Agent\\Toolbox\\Toolbox` instance on first
    call to ``getTools()``. In a typical PHP-FPM request the toolbox is recreated each time, so
    providers are re-invoked per request. In a long-running process (worker, daemon) the cached
    schema lives as long as the toolbox instance, so changes to the underlying values are not
    picked up until the worker restarts.

.. note::

    The enricher does not validate the shape of the fragment returned by ``getSchemaFragment()``.
    Returning a malformed JSON Schema produces a malformed schema sent to the LLM — stick to
    documented JSON Schema keys.

Learn More
----------

* :doc:`../components/agent` - Tools, processors, and structured output
* :doc:`../components/platform` - The JSON Schema factory
* :doc:`../bundles/ai-bundle` - Auto-wiring providers as Symfony services
