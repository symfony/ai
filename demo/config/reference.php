<?php

// This file is auto-generated and is for apps only. Bundles SHOULD NOT rely on its content.

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

/**
 * This class provides array-shapes for configuring the services and bundles of an application.
 *
 * Services declared with the config() method below are autowired and autoconfigured by default.
 *
 * This is for apps only. Bundles SHOULD NOT use it.
 *
 * Example:
 *
 *     ```php
 *     // config/services.php
 *     namespace Symfony\Component\DependencyInjection\Loader\Configurator;
 *
 *     return App::config([
 *         'services' => [
 *             'App\\' => [
 *                 'resource' => '../src/',
 *             ],
 *         ],
 *     ]);
 *     ```
 *
 * @psalm-type ImportsConfig = list<string|array{
 *     resource: string,
 *     type?: string|null,
 *     ignore_errors?: bool,
 * }>
 * @psalm-type ParametersConfig = array<string, scalar|\UnitEnum|array<scalar|\UnitEnum|array<mixed>|null>|null>
 * @psalm-type ArgumentsType = list<mixed>|array<string, mixed>
 * @psalm-type CallType = array<string, ArgumentsType>|array{0:string, 1?:ArgumentsType, 2?:bool}|array{method:string, arguments?:ArgumentsType, returns_clone?:bool}
 * @psalm-type TagsType = list<string|array<string, array<string, mixed>>> // arrays inside the list must have only one element, with the tag name as the key
 * @psalm-type CallbackType = string|array{0:string|ReferenceConfigurator,1:string}|\Closure|ReferenceConfigurator|ExpressionConfigurator
 * @psalm-type DeprecationType = array{package: string, version: string, message?: string}
 * @psalm-type DefaultsType = array{
 *     public?: bool,
 *     tags?: TagsType,
 *     resource_tags?: TagsType,
 *     autowire?: bool,
 *     autoconfigure?: bool,
 *     bind?: array<string, mixed>,
 * }
 * @psalm-type InstanceofType = array{
 *     shared?: bool,
 *     lazy?: bool|string,
 *     public?: bool,
 *     properties?: array<string, mixed>,
 *     configurator?: CallbackType,
 *     calls?: list<CallType>,
 *     tags?: TagsType,
 *     resource_tags?: TagsType,
 *     autowire?: bool,
 *     bind?: array<string, mixed>,
 *     constructor?: string,
 * }
 * @psalm-type DefinitionType = array{
 *     class?: string,
 *     file?: string,
 *     parent?: string,
 *     shared?: bool,
 *     synthetic?: bool,
 *     lazy?: bool|string,
 *     public?: bool,
 *     abstract?: bool,
 *     deprecated?: DeprecationType,
 *     factory?: CallbackType,
 *     configurator?: CallbackType,
 *     arguments?: ArgumentsType,
 *     properties?: array<string, mixed>,
 *     calls?: list<CallType>,
 *     tags?: TagsType,
 *     resource_tags?: TagsType,
 *     decorates?: string,
 *     decoration_inner_name?: string,
 *     decoration_priority?: int,
 *     decoration_on_invalid?: 'exception'|'ignore'|null,
 *     autowire?: bool,
 *     autoconfigure?: bool,
 *     bind?: array<string, mixed>,
 *     constructor?: string,
 *     from_callable?: CallbackType,
 * }
 * @psalm-type AliasType = string|array{
 *     alias: string,
 *     public?: bool,
 *     deprecated?: DeprecationType,
 * }
 * @psalm-type PrototypeType = array{
 *     resource: string,
 *     namespace?: string,
 *     exclude?: string|list<string>,
 *     parent?: string,
 *     shared?: bool,
 *     lazy?: bool|string,
 *     public?: bool,
 *     abstract?: bool,
 *     deprecated?: DeprecationType,
 *     factory?: CallbackType,
 *     arguments?: ArgumentsType,
 *     properties?: array<string, mixed>,
 *     configurator?: CallbackType,
 *     calls?: list<CallType>,
 *     tags?: TagsType,
 *     resource_tags?: TagsType,
 *     autowire?: bool,
 *     autoconfigure?: bool,
 *     bind?: array<string, mixed>,
 *     constructor?: string,
 * }
 * @psalm-type StackType = array{
 *     stack: list<DefinitionType|AliasType|PrototypeType|array<class-string, ArgumentsType|null>>,
 *     public?: bool,
 *     deprecated?: DeprecationType,
 * }
 * @psalm-type ServicesConfig = array{
 *     _defaults?: DefaultsType,
 *     _instanceof?: InstanceofType,
 *     ...<string, DefinitionType|AliasType|PrototypeType|StackType|ArgumentsType|null>
 * }
 * @psalm-type ExtensionType = array<string, mixed>
 * @psalm-type AiConfig = array{
 *     platform?: array{
 *         albert?: array{
 *             api_key: string,
 *             base_url: string,
 *             http_client?: string, // Service ID of the HTTP client to use // Default: "http_client"
 *         },
 *         anthropic?: array{
 *             api_key: string,
 *             version?: string, // Default: null
 *             http_client?: string, // Service ID of the HTTP client to use // Default: "http_client"
 *         },
 *         azure?: array<string, array{ // Default: []
 *             api_key: string,
 *             base_url: string,
 *             deployment: string,
 *             api_version?: string, // The used API version
 *             http_client?: string, // Service ID of the HTTP client to use // Default: "http_client"
 *         }>,
 *         cache?: array<string, array{ // Default: []
 *             platform: string,
 *             service: string, // The cache service id as defined under the "cache" configuration key
 *             cache_key?: string, // Key used to store platform results, if not set, the current platform name will be used, the "prompt_cache_key" can be set during platform call to override this value
 *         }>,
 *         cartesia?: array{
 *             api_key: string,
 *             version: string,
 *             http_client?: string, // Service ID of the HTTP client to use // Default: "http_client"
 *         },
 *         elevenlabs?: array{
 *             api_key: string,
 *             host?: string, // Default: "https://api.elevenlabs.io/v1"
 *             http_client?: string, // Service ID of the HTTP client to use // Default: "http_client"
 *         },
 *         gemini?: array{
 *             api_key: string,
 *             http_client?: string, // Service ID of the HTTP client to use // Default: "http_client"
 *         },
 *         generic?: array<string, array{ // Default: []
 *             base_url: string,
 *             api_key?: string,
 *             http_client?: string, // Service ID of the HTTP client to use // Default: "http_client"
 *             model_catalog?: string, // Service ID of the model catalog to use
 *             supports_completions?: bool, // Default: true
 *             supports_embeddings?: bool, // Default: true
 *             completions_path?: string, // Default: "/v1/chat/completions"
 *             embeddings_path?: string, // Default: "/v1/embeddings"
 *         }>,
 *         huggingface?: array{
 *             api_key: string,
 *             provider?: string, // Default: "hf-inference"
 *             http_client?: string, // Service ID of the HTTP client to use // Default: "http_client"
 *         },
 *         vertexai?: array{
 *             location: string,
 *             project_id: string,
 *         },
 *         openai?: array{
 *             api_key: string,
 *             region?: scalar|null, // The region for OpenAI API (EU, US, or null for default) // Default: null
 *             http_client?: string, // Service ID of the HTTP client to use // Default: "http_client"
 *         },
 *         mistral?: array{
 *             api_key: string,
 *             http_client?: string, // Service ID of the HTTP client to use // Default: "http_client"
 *         },
 *         openrouter?: array{
 *             api_key: string,
 *             http_client?: string, // Service ID of the HTTP client to use // Default: "http_client"
 *         },
 *         lmstudio?: array{
 *             host_url?: string, // Default: "http://127.0.0.1:1234"
 *             http_client?: string, // Service ID of the HTTP client to use // Default: "http_client"
 *         },
 *         ollama?: array{
 *             host_url?: string, // Default: "http://127.0.0.1:11434"
 *             http_client?: string, // Service ID of the HTTP client to use // Default: "http_client"
 *             api_catalog?: bool, // If set, the Ollama API will be used to build the catalog and retrieve models information, using this option leads to additional HTTP calls
 *         },
 *         cerebras?: array{
 *             api_key: string,
 *             http_client?: string, // Service ID of the HTTP client to use // Default: "http_client"
 *         },
 *         voyage?: array{
 *             api_key: string,
 *             http_client?: string, // Service ID of the HTTP client to use // Default: "http_client"
 *         },
 *         perplexity?: array{
 *             api_key: string,
 *             http_client?: string, // Service ID of the HTTP client to use // Default: "http_client"
 *         },
 *         dockermodelrunner?: array{
 *             host_url?: string, // Default: "http://127.0.0.1:12434"
 *             http_client?: string, // Service ID of the HTTP client to use // Default: "http_client"
 *         },
 *         scaleway?: array{
 *             api_key: scalar|null,
 *             http_client?: string, // Service ID of the HTTP client to use // Default: "http_client"
 *         },
 *     },
 *     model?: array<string, array<string, array{ // Default: []
 *             class?: string, // The fully qualified class name of the model (must extend Symfony\AI\Platform\Model) // Default: "Symfony\\AI\\Platform\\Model"
 *             capabilities?: list<value-of<\Symfony\AI\Platform\Capability>|\Symfony\AI\Platform\Capability>,
 *         }>>,
 *     agent?: array<string, array{ // Default: []
 *         platform?: string, // Service name of platform // Default: "Symfony\\AI\\Platform\\PlatformInterface"
 *         track_token_usage?: bool, // Enable tracking of token usage for the agent // Default: true
 *         model?: mixed,
 *         memory?: mixed, // Memory configuration: string for static memory, or array with "service" key for service reference // Default: null
 *         prompt?: string|array{ // The system prompt configuration
 *             text?: string, // The system prompt text
 *             file?: string, // Path to file containing the system prompt
 *             include_tools?: bool, // Include tool definitions at the end of the system prompt // Default: false
 *             enable_translation?: bool, // Enable translation for the system prompt // Default: false
 *             translation_domain?: string, // The translation domain for the system prompt // Default: null
 *         },
 *         tools?: bool|array{
 *             enabled?: bool, // Default: true
 *             services?: list<string|array{ // Default: []
 *                 service?: string,
 *                 agent?: string,
 *                 name?: string,
 *                 description?: string,
 *                 method?: string,
 *             }>,
 *         },
 *         keep_tool_messages?: bool, // Keep tool messages in the conversation history // Default: false
 *         include_sources?: bool, // Include sources exposed by tools as part of the tool result metadata // Default: false
 *         fault_tolerant_toolbox?: bool, // Continue the agent run even if a tool call fails // Default: true
 *     }>,
 *     multi_agent?: array<string, array{ // Default: []
 *         orchestrator: string, // Service ID of the orchestrator agent
 *         handoffs: array<string, list<scalar|null>>,
 *         fallback: string, // Service ID of the fallback agent for unmatched requests
 *     }>,
 *     store?: array{
 *         azuresearch?: array<string, array{ // Default: []
 *             endpoint: string,
 *             api_key: string,
 *             index_name: string,
 *             api_version: string,
 *             vector_field?: string,
 *         }>,
 *         cache?: array<string, array{ // Default: []
 *             service?: string, // Default: "cache.app"
 *             cache_key?: string, // The name of the store will be used if the key is not set
 *             strategy?: string,
 *         }>,
 *         chromadb?: array<string, array{ // Default: []
 *             client?: string, // Default: "Codewithkyrian\\ChromaDB\\Client"
 *             collection: string,
 *         }>,
 *         clickhouse?: array<string, array{ // Default: []
 *             dsn?: string,
 *             http_client?: string,
 *             database: string,
 *             table: string,
 *         }>,
 *         cloudflare?: array<string, array{ // Default: []
 *             account_id?: string,
 *             api_key?: string,
 *             index_name?: string,
 *             dimensions?: int, // Default: 1536
 *             metric?: string, // Default: "cosine"
 *             endpoint?: string,
 *         }>,
 *         manticoresearch?: array<string, array{ // Default: []
 *             endpoint?: string,
 *             table?: string,
 *             field?: string, // Default: "_vectors"
 *             type?: string, // Default: "hnsw"
 *             similarity?: string, // Default: "cosine"
 *             dimensions?: int, // Default: 1536
 *             quantization?: string,
 *         }>,
 *         mariadb?: array<string, array{ // Default: []
 *             connection?: string,
 *             table_name?: string,
 *             index_name?: string,
 *             vector_field_name?: string,
 *             setup_options?: array{
 *                 dimensions?: int,
 *             },
 *         }>,
 *         meilisearch?: array<string, array{ // Default: []
 *             endpoint?: string,
 *             api_key?: string,
 *             index_name?: string,
 *             embedder?: string, // Default: "default"
 *             vector_field?: string, // Default: "_vectors"
 *             dimensions?: int, // Default: 1536
 *             semantic_ratio?: float, // The ratio between semantic (vector) and full-text search (0.0 to 1.0). Default: 1.0 (100% semantic) // Default: 1.0
 *         }>,
 *         memory?: array<string, array{ // Default: []
 *             strategy?: string,
 *         }>,
 *         milvus?: array<string, array{ // Default: []
 *             endpoint?: string,
 *             api_key: string,
 *             database?: string,
 *             collection: string,
 *             vector_field?: string, // Default: "_vectors"
 *             dimensions?: int, // Default: 1536
 *             metric_type?: string, // Default: "COSINE"
 *         }>,
 *         mongodb?: array<string, array{ // Default: []
 *             client?: string, // Default: "MongoDB\\Client"
 *             database: string,
 *             collection?: string,
 *             index_name: string,
 *             vector_field?: string, // Default: "vector"
 *             bulk_write?: bool, // Default: false
 *         }>,
 *         neo4j?: array<string, array{ // Default: []
 *             endpoint?: string,
 *             username?: string,
 *             password?: string,
 *             database?: string,
 *             vector_index_name?: string,
 *             node_name?: string,
 *             vector_field?: string, // Default: "embeddings"
 *             dimensions?: int, // Default: 1536
 *             distance?: string, // Default: "cosine"
 *             quantization?: bool,
 *         }>,
 *         opensearch?: array<string, array{ // Default: []
 *             endpoint?: string,
 *             index_name?: string,
 *             vectors_field?: string, // Default: "_vectors"
 *             dimensions?: int, // Default: 1536
 *             space_type?: string, // Default: "l2"
 *             http_client?: string, // Default: "http_client"
 *         }>,
 *         pinecone?: array<string, array{ // Default: []
 *             client?: string, // Default: "Probots\\Pinecone\\Client"
 *             namespace?: string,
 *             filter?: list<scalar|null>,
 *             top_k?: int,
 *         }>,
 *         postgres?: array<string, array{ // Default: []
 *             dsn?: string,
 *             username?: string,
 *             password?: string,
 *             table_name?: string,
 *             vector_field?: string, // Default: "embedding"
 *             distance?: "cosine"|"inner_product"|"l1"|"l2", // Distance metric to use for vector similarity search // Default: "l2"
 *             dbal_connection?: string,
 *         }>,
 *         qdrant?: array<string, array{ // Default: []
 *             endpoint?: string,
 *             api_key?: string,
 *             collection_name?: string,
 *             dimensions?: int, // Default: 1536
 *             distance?: string, // Default: "Cosine"
 *             async?: bool,
 *         }>,
 *         redis?: array<string, array{ // Default: []
 *             connection_parameters?: mixed, // see https://github.com/phpredis/phpredis?tab=readme-ov-file#example-1
 *             client?: string, // a service id of a Redis client
 *             index_name?: string,
 *             key_prefix?: string, // Default: "vector:"
 *             distance?: "COSINE"|"L2"|"IP", // Distance metric to use for vector similarity search // Default: "COSINE"
 *         }>,
 *         supabase?: array<string, array{ // Default: []
 *             http_client?: string, // Service ID of the HTTP client to use // Default: "http_client"
 *             url: string,
 *             api_key: string,
 *             table?: string,
 *             vector_field?: string, // Default: "embedding"
 *             vector_dimension?: int, // Default: 1536
 *             function_name?: string, // Default: "match_documents"
 *         }>,
 *         surrealdb?: array<string, array{ // Default: []
 *             endpoint?: string,
 *             username?: string,
 *             password?: string,
 *             namespace?: string,
 *             database?: string,
 *             table?: string,
 *             vector_field?: string, // Default: "_vectors"
 *             strategy?: string, // Default: "cosine"
 *             dimensions?: int, // Default: 1536
 *             namespaced_user?: bool,
 *         }>,
 *         typesense?: array<string, array{ // Default: []
 *             endpoint?: string,
 *             api_key: string,
 *             collection?: string,
 *             vector_field?: string, // Default: "_vectors"
 *             dimensions?: int, // Default: 1536
 *         }>,
 *         weaviate?: array<string, array{ // Default: []
 *             endpoint?: string,
 *             api_key: string,
 *             collection?: string,
 *         }>,
 *     },
 *     message_store?: array{
 *         cache?: array<string, array{ // Default: []
 *             service?: string, // Default: "cache.app"
 *             key?: string, // The name of the message store will be used if the key is not set
 *             ttl?: int,
 *         }>,
 *         cloudflare?: array<string, array{ // Default: []
 *             account_id?: string,
 *             api_key?: string,
 *             namespace?: string,
 *             endpoint_url?: string, // If the version of the Cloudflare API is updated, use this key to support it.
 *         }>,
 *         doctrine?: array{
 *             dbal?: array<string, array{ // Default: []
 *                 connection?: string,
 *                 table_name?: string, // The name of the message store will be used if the table_name is not set
 *             }>,
 *         },
 *         meilisearch?: array<string, array{ // Default: []
 *             endpoint?: string,
 *             api_key?: string,
 *             index_name?: string,
 *         }>,
 *         memory?: array<string, array{ // Default: []
 *             identifier?: string,
 *         }>,
 *         mongodb?: array<string, array{ // Default: []
 *             client?: string, // Default: "MongoDB\\Client"
 *             database: string,
 *             collection: string,
 *         }>,
 *         pogocache?: array<string, array{ // Default: []
 *             endpoint?: string,
 *             password?: string,
 *             key?: string,
 *         }>,
 *         redis?: array<string, array{ // Default: []
 *             connection_parameters?: mixed, // see https://github.com/phpredis/phpredis?tab=readme-ov-file#example-1
 *             client?: string, // a service id of a Redis client
 *             endpoint?: string,
 *             index_name?: string,
 *         }>,
 *         session?: array<string, array{ // Default: []
 *             identifier?: string,
 *         }>,
 *         surrealdb?: array<string, array{ // Default: []
 *             endpoint?: string,
 *             username?: string,
 *             password?: string,
 *             namespace?: string,
 *             database?: string,
 *             table?: string,
 *             namespaced_user?: bool, // Using a namespaced user is a good practice to prevent any undesired access to a specific table, see https://surrealdb.com/docs/surrealdb/reference-guide/security-best-practices
 *         }>,
 *     },
 *     chat?: array<string, array{ // Default: []
 *         agent?: string,
 *         message_store?: string,
 *     }>,
 *     vectorizer?: array<string, array{ // Default: []
 *         platform?: string, // Service name of platform // Default: "Symfony\\AI\\Platform\\PlatformInterface"
 *         model?: mixed,
 *     }>,
 *     indexer?: array<string, array{ // Default: []
 *         loader: string, // Service name of loader
 *         source?: mixed, // Source identifier (file path, URL, etc.) or array of sources // Default: null
 *         transformers?: list<scalar|null>,
 *         filters?: list<scalar|null>,
 *         vectorizer?: scalar|null, // Service name of vectorizer // Default: "Symfony\\AI\\Store\\Document\\VectorizerInterface"
 *         store?: string, // Service name of store // Default: "Symfony\\AI\\Store\\StoreInterface"
 *     }>,
 *     retriever?: array<string, array{ // Default: []
 *         vectorizer?: scalar|null, // Service name of vectorizer // Default: "Symfony\\AI\\Store\\Document\\VectorizerInterface"
 *         store?: string, // Service name of store // Default: "Symfony\\AI\\Store\\StoreInterface"
 *     }>,
 * }
 * @psalm-type McpConfig = array{
 *     app?: scalar|null, // Default: "app"
 *     version?: scalar|null, // Default: "0.0.1"
 *     pagination_limit?: int, // Default: 50
 *     instructions?: scalar|null, // Default: null
 *     client_transports?: array{
 *         stdio?: bool, // Default: false
 *         http?: bool, // Default: false
 *     },
 *     discovery?: array{
 *         scan_dirs?: list<scalar|null>,
 *         exclude_dirs?: list<scalar|null>,
 *     },
 *     http?: array{
 *         path?: scalar|null, // Default: "/_mcp"
 *         session?: array{
 *             store?: "file"|"memory", // Default: "file"
 *             directory?: scalar|null, // Default: "%kernel.cache_dir%/mcp-sessions"
 *             ttl?: int, // Default: 3600
 *         },
 *     },
 * }
 * @psalm-type DebugConfig = array{
 *     max_items?: int, // Max number of displayed items past the first level, -1 means no limit. // Default: 2500
 *     min_depth?: int, // Minimum tree depth to clone all the items, 1 is default. // Default: 1
 *     max_string_length?: int, // Max length of displayed strings, -1 means no limit. // Default: -1
 *     dump_destination?: scalar|null, // A stream URL where dumps should be written to. // Default: null
 *     theme?: "dark"|"light", // Changes the color of the dump() output when rendered directly on the templating. "dark" (default) or "light". // Default: "dark"
 * }
 * @psalm-type FrameworkConfig = array{
 *     secret?: scalar|null,
 *     http_method_override?: bool, // Set true to enable support for the '_method' request parameter to determine the intended HTTP method on POST requests. // Default: false
 *     allowed_http_method_override?: list<string>|null,
 *     trust_x_sendfile_type_header?: scalar|null, // Set true to enable support for xsendfile in binary file responses. // Default: "%env(bool:default::SYMFONY_TRUST_X_SENDFILE_TYPE_HEADER)%"
 *     ide?: scalar|null, // Default: "%env(default::SYMFONY_IDE)%"
 *     test?: bool,
 *     default_locale?: scalar|null, // Default: "en"
 *     set_locale_from_accept_language?: bool, // Whether to use the Accept-Language HTTP header to set the Request locale (only when the "_locale" request attribute is not passed). // Default: false
 *     set_content_language_from_locale?: bool, // Whether to set the Content-Language HTTP header on the Response using the Request locale. // Default: false
 *     enabled_locales?: list<scalar|null>,
 *     trusted_hosts?: list<scalar|null>,
 *     trusted_proxies?: mixed, // Default: ["%env(default::SYMFONY_TRUSTED_PROXIES)%"]
 *     trusted_headers?: list<scalar|null>,
 *     error_controller?: scalar|null, // Default: "error_controller"
 *     handle_all_throwables?: bool, // HttpKernel will handle all kinds of \Throwable. // Default: true
 *     csrf_protection?: bool|array{
 *         enabled?: scalar|null, // Default: null
 *         stateless_token_ids?: list<scalar|null>,
 *         check_header?: scalar|null, // Whether to check the CSRF token in a header in addition to a cookie when using stateless protection. // Default: false
 *         cookie_name?: scalar|null, // The name of the cookie to use when using stateless protection. // Default: "csrf-token"
 *     },
 *     form?: bool|array{ // Form configuration
 *         enabled?: bool, // Default: true
 *         csrf_protection?: array{
 *             enabled?: scalar|null, // Default: null
 *             token_id?: scalar|null, // Default: null
 *             field_name?: scalar|null, // Default: "_token"
 *             field_attr?: array<string, scalar|null>,
 *         },
 *     },
 *     http_cache?: bool|array{ // HTTP cache configuration
 *         enabled?: bool, // Default: false
 *         debug?: bool, // Default: "%kernel.debug%"
 *         trace_level?: "none"|"short"|"full",
 *         trace_header?: scalar|null,
 *         default_ttl?: int,
 *         private_headers?: list<scalar|null>,
 *         skip_response_headers?: list<scalar|null>,
 *         allow_reload?: bool,
 *         allow_revalidate?: bool,
 *         stale_while_revalidate?: int,
 *         stale_if_error?: int,
 *         terminate_on_cache_hit?: bool,
 *     },
 *     esi?: bool|array{ // ESI configuration
 *         enabled?: bool, // Default: false
 *     },
 *     ssi?: bool|array{ // SSI configuration
 *         enabled?: bool, // Default: false
 *     },
 *     fragments?: bool|array{ // Fragments configuration
 *         enabled?: bool, // Default: false
 *         hinclude_default_template?: scalar|null, // Default: null
 *         path?: scalar|null, // Default: "/_fragment"
 *     },
 *     profiler?: bool|array{ // Profiler configuration
 *         enabled?: bool, // Default: false
 *         collect?: bool, // Default: true
 *         collect_parameter?: scalar|null, // The name of the parameter to use to enable or disable collection on a per request basis. // Default: null
 *         only_exceptions?: bool, // Default: false
 *         only_main_requests?: bool, // Default: false
 *         dsn?: scalar|null, // Default: "file:%kernel.cache_dir%/profiler"
 *         collect_serializer_data?: true, // Default: true
 *     },
 *     workflows?: bool|array{
 *         enabled?: bool, // Default: false
 *         workflows?: array<string, array{ // Default: []
 *             audit_trail?: bool|array{
 *                 enabled?: bool, // Default: false
 *             },
 *             type?: "workflow"|"state_machine", // Default: "state_machine"
 *             marking_store?: array{
 *                 type?: "method",
 *                 property?: scalar|null,
 *                 service?: scalar|null,
 *             },
 *             supports?: list<scalar|null>,
 *             definition_validators?: list<scalar|null>,
 *             support_strategy?: scalar|null,
 *             initial_marking?: list<scalar|null>,
 *             events_to_dispatch?: list<string>|null,
 *             places?: list<array{ // Default: []
 *                 name: scalar|null,
 *                 metadata?: list<mixed>,
 *             }>,
 *             transitions: list<array{ // Default: []
 *                 name: string,
 *                 guard?: string, // An expression to block the transition.
 *                 from?: list<array{ // Default: []
 *                     place: string,
 *                     weight?: int, // Default: 1
 *                 }>,
 *                 to?: list<array{ // Default: []
 *                     place: string,
 *                     weight?: int, // Default: 1
 *                 }>,
 *                 weight?: int, // Default: 1
 *                 metadata?: list<mixed>,
 *             }>,
 *             metadata?: list<mixed>,
 *         }>,
 *     },
 *     router?: bool|array{ // Router configuration
 *         enabled?: bool, // Default: false
 *         resource: scalar|null,
 *         type?: scalar|null,
 *         default_uri?: scalar|null, // The default URI used to generate URLs in a non-HTTP context. // Default: null
 *         http_port?: scalar|null, // Default: 80
 *         https_port?: scalar|null, // Default: 443
 *         strict_requirements?: scalar|null, // set to true to throw an exception when a parameter does not match the requirements set to false to disable exceptions when a parameter does not match the requirements (and return null instead) set to null to disable parameter checks against requirements 'true' is the preferred configuration in development mode, while 'false' or 'null' might be preferred in production // Default: true
 *         utf8?: bool, // Default: true
 *     },
 *     session?: bool|array{ // Session configuration
 *         enabled?: bool, // Default: false
 *         storage_factory_id?: scalar|null, // Default: "session.storage.factory.native"
 *         handler_id?: scalar|null, // Defaults to using the native session handler, or to the native *file* session handler if "save_path" is not null.
 *         name?: scalar|null,
 *         cookie_lifetime?: scalar|null,
 *         cookie_path?: scalar|null,
 *         cookie_domain?: scalar|null,
 *         cookie_secure?: true|false|"auto", // Default: "auto"
 *         cookie_httponly?: bool, // Default: true
 *         cookie_samesite?: null|"lax"|"strict"|"none", // Default: "lax"
 *         use_cookies?: bool,
 *         gc_divisor?: scalar|null,
 *         gc_probability?: scalar|null,
 *         gc_maxlifetime?: scalar|null,
 *         save_path?: scalar|null, // Defaults to "%kernel.cache_dir%/sessions" if the "handler_id" option is not null.
 *         metadata_update_threshold?: int, // Seconds to wait between 2 session metadata updates. // Default: 0
 *     },
 *     request?: bool|array{ // Request configuration
 *         enabled?: bool, // Default: false
 *         formats?: array<string, string|list<scalar|null>>,
 *     },
 *     assets?: bool|array{ // Assets configuration
 *         enabled?: bool, // Default: true
 *         strict_mode?: bool, // Throw an exception if an entry is missing from the manifest.json. // Default: false
 *         version_strategy?: scalar|null, // Default: null
 *         version?: scalar|null, // Default: null
 *         version_format?: scalar|null, // Default: "%%s?%%s"
 *         json_manifest_path?: scalar|null, // Default: null
 *         base_path?: scalar|null, // Default: ""
 *         base_urls?: list<scalar|null>,
 *         packages?: array<string, array{ // Default: []
 *             strict_mode?: bool, // Throw an exception if an entry is missing from the manifest.json. // Default: false
 *             version_strategy?: scalar|null, // Default: null
 *             version?: scalar|null,
 *             version_format?: scalar|null, // Default: null
 *             json_manifest_path?: scalar|null, // Default: null
 *             base_path?: scalar|null, // Default: ""
 *             base_urls?: list<scalar|null>,
 *         }>,
 *     },
 *     asset_mapper?: bool|array{ // Asset Mapper configuration
 *         enabled?: bool, // Default: true
 *         paths?: array<string, scalar|null>,
 *         excluded_patterns?: list<scalar|null>,
 *         exclude_dotfiles?: bool, // If true, any files starting with "." will be excluded from the asset mapper. // Default: true
 *         server?: bool, // If true, a "dev server" will return the assets from the public directory (true in "debug" mode only by default). // Default: true
 *         public_prefix?: scalar|null, // The public path where the assets will be written to (and served from when "server" is true). // Default: "/assets/"
 *         missing_import_mode?: "strict"|"warn"|"ignore", // Behavior if an asset cannot be found when imported from JavaScript or CSS files - e.g. "import './non-existent.js'". "strict" means an exception is thrown, "warn" means a warning is logged, "ignore" means the import is left as-is. // Default: "warn"
 *         extensions?: array<string, scalar|null>,
 *         importmap_path?: scalar|null, // The path of the importmap.php file. // Default: "%kernel.project_dir%/importmap.php"
 *         importmap_polyfill?: scalar|null, // The importmap name that will be used to load the polyfill. Set to false to disable. // Default: "es-module-shims"
 *         importmap_script_attributes?: array<string, scalar|null>,
 *         vendor_dir?: scalar|null, // The directory to store JavaScript vendors. // Default: "%kernel.project_dir%/assets/vendor"
 *         precompress?: bool|array{ // Precompress assets with Brotli, Zstandard and gzip.
 *             enabled?: bool, // Default: false
 *             formats?: list<scalar|null>,
 *             extensions?: list<scalar|null>,
 *         },
 *     },
 *     translator?: bool|array{ // Translator configuration
 *         enabled?: bool, // Default: false
 *         fallbacks?: list<scalar|null>,
 *         logging?: bool, // Default: false
 *         formatter?: scalar|null, // Default: "translator.formatter.default"
 *         cache_dir?: scalar|null, // Default: "%kernel.cache_dir%/translations"
 *         default_path?: scalar|null, // The default path used to load translations. // Default: "%kernel.project_dir%/translations"
 *         paths?: list<scalar|null>,
 *         pseudo_localization?: bool|array{
 *             enabled?: bool, // Default: false
 *             accents?: bool, // Default: true
 *             expansion_factor?: float, // Default: 1.0
 *             brackets?: bool, // Default: true
 *             parse_html?: bool, // Default: false
 *             localizable_html_attributes?: list<scalar|null>,
 *         },
 *         providers?: array<string, array{ // Default: []
 *             dsn?: scalar|null,
 *             domains?: list<scalar|null>,
 *             locales?: list<scalar|null>,
 *         }>,
 *         globals?: array<string, string|array{ // Default: []
 *             value?: mixed,
 *             message?: string,
 *             parameters?: array<string, scalar|null>,
 *             domain?: string,
 *         }>,
 *     },
 *     validation?: bool|array{ // Validation configuration
 *         enabled?: bool, // Default: false
 *         enable_attributes?: bool, // Default: true
 *         static_method?: list<scalar|null>,
 *         translation_domain?: scalar|null, // Default: "validators"
 *         email_validation_mode?: "html5"|"html5-allow-no-tld"|"strict", // Default: "html5"
 *         mapping?: array{
 *             paths?: list<scalar|null>,
 *         },
 *         not_compromised_password?: bool|array{
 *             enabled?: bool, // When disabled, compromised passwords will be accepted as valid. // Default: true
 *             endpoint?: scalar|null, // API endpoint for the NotCompromisedPassword Validator. // Default: null
 *         },
 *         disable_translation?: bool, // Default: false
 *         auto_mapping?: array<string, array{ // Default: []
 *             services?: list<scalar|null>,
 *         }>,
 *     },
 *     serializer?: bool|array{ // Serializer configuration
 *         enabled?: bool, // Default: true
 *         enable_attributes?: bool, // Default: true
 *         name_converter?: scalar|null,
 *         circular_reference_handler?: scalar|null,
 *         max_depth_handler?: scalar|null,
 *         mapping?: array{
 *             paths?: list<scalar|null>,
 *         },
 *         default_context?: list<mixed>,
 *         named_serializers?: array<string, array{ // Default: []
 *             name_converter?: scalar|null,
 *             default_context?: list<mixed>,
 *             include_built_in_normalizers?: bool, // Whether to include the built-in normalizers // Default: true
 *             include_built_in_encoders?: bool, // Whether to include the built-in encoders // Default: true
 *         }>,
 *     },
 *     property_access?: bool|array{ // Property access configuration
 *         enabled?: bool, // Default: true
 *         magic_call?: bool, // Default: false
 *         magic_get?: bool, // Default: true
 *         magic_set?: bool, // Default: true
 *         throw_exception_on_invalid_index?: bool, // Default: false
 *         throw_exception_on_invalid_property_path?: bool, // Default: true
 *     },
 *     type_info?: bool|array{ // Type info configuration
 *         enabled?: bool, // Default: true
 *         aliases?: array<string, scalar|null>,
 *     },
 *     property_info?: bool|array{ // Property info configuration
 *         enabled?: bool, // Default: true
 *         with_constructor_extractor?: bool, // Registers the constructor extractor. // Default: true
 *     },
 *     cache?: array{ // Cache configuration
 *         prefix_seed?: scalar|null, // Used to namespace cache keys when using several apps with the same shared backend. // Default: "_%kernel.project_dir%.%kernel.container_class%"
 *         app?: scalar|null, // App related cache pools configuration. // Default: "cache.adapter.filesystem"
 *         system?: scalar|null, // System related cache pools configuration. // Default: "cache.adapter.system"
 *         directory?: scalar|null, // Default: "%kernel.share_dir%/pools/app"
 *         default_psr6_provider?: scalar|null,
 *         default_redis_provider?: scalar|null, // Default: "redis://localhost"
 *         default_valkey_provider?: scalar|null, // Default: "valkey://localhost"
 *         default_memcached_provider?: scalar|null, // Default: "memcached://localhost"
 *         default_doctrine_dbal_provider?: scalar|null, // Default: "database_connection"
 *         default_pdo_provider?: scalar|null, // Default: null
 *         pools?: array<string, array{ // Default: []
 *             adapters?: list<scalar|null>,
 *             tags?: scalar|null, // Default: null
 *             public?: bool, // Default: false
 *             default_lifetime?: scalar|null, // Default lifetime of the pool.
 *             provider?: scalar|null, // Overwrite the setting from the default provider for this adapter.
 *             early_expiration_message_bus?: scalar|null,
 *             clearer?: scalar|null,
 *         }>,
 *     },
 *     php_errors?: array{ // PHP errors handling configuration
 *         log?: mixed, // Use the application logger instead of the PHP logger for logging PHP errors. // Default: true
 *         throw?: bool, // Throw PHP errors as \ErrorException instances. // Default: true
 *     },
 *     exceptions?: array<string, array{ // Default: []
 *         log_level?: scalar|null, // The level of log message. Null to let Symfony decide. // Default: null
 *         status_code?: scalar|null, // The status code of the response. Null or 0 to let Symfony decide. // Default: null
 *         log_channel?: scalar|null, // The channel of log message. Null to let Symfony decide. // Default: null
 *     }>,
 *     web_link?: bool|array{ // Web links configuration
 *         enabled?: bool, // Default: false
 *     },
 *     lock?: bool|string|array{ // Lock configuration
 *         enabled?: bool, // Default: false
 *         resources?: array<string, string|list<scalar|null>>,
 *     },
 *     semaphore?: bool|string|array{ // Semaphore configuration
 *         enabled?: bool, // Default: false
 *         resources?: array<string, scalar|null>,
 *     },
 *     messenger?: bool|array{ // Messenger configuration
 *         enabled?: bool, // Default: false
 *         routing?: array<string, array{ // Default: []
 *             senders?: list<scalar|null>,
 *         }>,
 *         serializer?: array{
 *             default_serializer?: scalar|null, // Service id to use as the default serializer for the transports. // Default: "messenger.transport.native_php_serializer"
 *             symfony_serializer?: array{
 *                 format?: scalar|null, // Serialization format for the messenger.transport.symfony_serializer service (which is not the serializer used by default). // Default: "json"
 *                 context?: array<string, mixed>,
 *             },
 *         },
 *         transports?: array<string, string|array{ // Default: []
 *             dsn?: scalar|null,
 *             serializer?: scalar|null, // Service id of a custom serializer to use. // Default: null
 *             options?: list<mixed>,
 *             failure_transport?: scalar|null, // Transport name to send failed messages to (after all retries have failed). // Default: null
 *             retry_strategy?: string|array{
 *                 service?: scalar|null, // Service id to override the retry strategy entirely. // Default: null
 *                 max_retries?: int, // Default: 3
 *                 delay?: int, // Time in ms to delay (or the initial value when multiplier is used). // Default: 1000
 *                 multiplier?: float, // If greater than 1, delay will grow exponentially for each retry: this delay = (delay * (multiple ^ retries)). // Default: 2
 *                 max_delay?: int, // Max time in ms that a retry should ever be delayed (0 = infinite). // Default: 0
 *                 jitter?: float, // Randomness to apply to the delay (between 0 and 1). // Default: 0.1
 *             },
 *             rate_limiter?: scalar|null, // Rate limiter name to use when processing messages. // Default: null
 *         }>,
 *         failure_transport?: scalar|null, // Transport name to send failed messages to (after all retries have failed). // Default: null
 *         stop_worker_on_signals?: list<scalar|null>,
 *         default_bus?: scalar|null, // Default: null
 *         buses?: array<string, array{ // Default: {"messenger.bus.default":{"default_middleware":{"enabled":true,"allow_no_handlers":false,"allow_no_senders":true},"middleware":[]}}
 *             default_middleware?: bool|string|array{
 *                 enabled?: bool, // Default: true
 *                 allow_no_handlers?: bool, // Default: false
 *                 allow_no_senders?: bool, // Default: true
 *             },
 *             middleware?: list<string|array{ // Default: []
 *                 id: scalar|null,
 *                 arguments?: list<mixed>,
 *             }>,
 *         }>,
 *     },
 *     scheduler?: bool|array{ // Scheduler configuration
 *         enabled?: bool, // Default: false
 *     },
 *     disallow_search_engine_index?: bool, // Enabled by default when debug is enabled. // Default: true
 *     http_client?: bool|array{ // HTTP Client configuration
 *         enabled?: bool, // Default: true
 *         max_host_connections?: int, // The maximum number of connections to a single host.
 *         default_options?: array{
 *             headers?: array<string, mixed>,
 *             vars?: array<string, mixed>,
 *             max_redirects?: int, // The maximum number of redirects to follow.
 *             http_version?: scalar|null, // The default HTTP version, typically 1.1 or 2.0, leave to null for the best version.
 *             resolve?: array<string, scalar|null>,
 *             proxy?: scalar|null, // The URL of the proxy to pass requests through or null for automatic detection.
 *             no_proxy?: scalar|null, // A comma separated list of hosts that do not require a proxy to be reached.
 *             timeout?: float, // The idle timeout, defaults to the "default_socket_timeout" ini parameter.
 *             max_duration?: float, // The maximum execution time for the request+response as a whole.
 *             bindto?: scalar|null, // A network interface name, IP address, a host name or a UNIX socket to bind to.
 *             verify_peer?: bool, // Indicates if the peer should be verified in a TLS context.
 *             verify_host?: bool, // Indicates if the host should exist as a certificate common name.
 *             cafile?: scalar|null, // A certificate authority file.
 *             capath?: scalar|null, // A directory that contains multiple certificate authority files.
 *             local_cert?: scalar|null, // A PEM formatted certificate file.
 *             local_pk?: scalar|null, // A private key file.
 *             passphrase?: scalar|null, // The passphrase used to encrypt the "local_pk" file.
 *             ciphers?: scalar|null, // A list of TLS ciphers separated by colons, commas or spaces (e.g. "RC3-SHA:TLS13-AES-128-GCM-SHA256"...)
 *             peer_fingerprint?: array{ // Associative array: hashing algorithm => hash(es).
 *                 sha1?: mixed,
 *                 pin-sha256?: mixed,
 *                 md5?: mixed,
 *             },
 *             crypto_method?: scalar|null, // The minimum version of TLS to accept; must be one of STREAM_CRYPTO_METHOD_TLSv*_CLIENT constants.
 *             extra?: array<string, mixed>,
 *             rate_limiter?: scalar|null, // Rate limiter name to use for throttling requests. // Default: null
 *             caching?: bool|array{ // Caching configuration.
 *                 enabled?: bool, // Default: false
 *                 cache_pool?: string, // The taggable cache pool to use for storing the responses. // Default: "cache.http_client"
 *                 shared?: bool, // Indicates whether the cache is shared (public) or private. // Default: true
 *                 max_ttl?: int, // The maximum TTL (in seconds) allowed for cached responses. Null means no cap. // Default: null
 *             },
 *             retry_failed?: bool|array{
 *                 enabled?: bool, // Default: false
 *                 retry_strategy?: scalar|null, // service id to override the retry strategy. // Default: null
 *                 http_codes?: array<string, array{ // Default: []
 *                     code?: int,
 *                     methods?: list<string>,
 *                 }>,
 *                 max_retries?: int, // Default: 3
 *                 delay?: int, // Time in ms to delay (or the initial value when multiplier is used). // Default: 1000
 *                 multiplier?: float, // If greater than 1, delay will grow exponentially for each retry: delay * (multiple ^ retries). // Default: 2
 *                 max_delay?: int, // Max time in ms that a retry should ever be delayed (0 = infinite). // Default: 0
 *                 jitter?: float, // Randomness in percent (between 0 and 1) to apply to the delay. // Default: 0.1
 *             },
 *         },
 *         mock_response_factory?: scalar|null, // The id of the service that should generate mock responses. It should be either an invokable or an iterable.
 *         scoped_clients?: array<string, string|array{ // Default: []
 *             scope?: scalar|null, // The regular expression that the request URL must match before adding the other options. When none is provided, the base URI is used instead.
 *             base_uri?: scalar|null, // The URI to resolve relative URLs, following rules in RFC 3985, section 2.
 *             auth_basic?: scalar|null, // An HTTP Basic authentication "username:password".
 *             auth_bearer?: scalar|null, // A token enabling HTTP Bearer authorization.
 *             auth_ntlm?: scalar|null, // A "username:password" pair to use Microsoft NTLM authentication (requires the cURL extension).
 *             query?: array<string, scalar|null>,
 *             headers?: array<string, mixed>,
 *             max_redirects?: int, // The maximum number of redirects to follow.
 *             http_version?: scalar|null, // The default HTTP version, typically 1.1 or 2.0, leave to null for the best version.
 *             resolve?: array<string, scalar|null>,
 *             proxy?: scalar|null, // The URL of the proxy to pass requests through or null for automatic detection.
 *             no_proxy?: scalar|null, // A comma separated list of hosts that do not require a proxy to be reached.
 *             timeout?: float, // The idle timeout, defaults to the "default_socket_timeout" ini parameter.
 *             max_duration?: float, // The maximum execution time for the request+response as a whole.
 *             bindto?: scalar|null, // A network interface name, IP address, a host name or a UNIX socket to bind to.
 *             verify_peer?: bool, // Indicates if the peer should be verified in a TLS context.
 *             verify_host?: bool, // Indicates if the host should exist as a certificate common name.
 *             cafile?: scalar|null, // A certificate authority file.
 *             capath?: scalar|null, // A directory that contains multiple certificate authority files.
 *             local_cert?: scalar|null, // A PEM formatted certificate file.
 *             local_pk?: scalar|null, // A private key file.
 *             passphrase?: scalar|null, // The passphrase used to encrypt the "local_pk" file.
 *             ciphers?: scalar|null, // A list of TLS ciphers separated by colons, commas or spaces (e.g. "RC3-SHA:TLS13-AES-128-GCM-SHA256"...).
 *             peer_fingerprint?: array{ // Associative array: hashing algorithm => hash(es).
 *                 sha1?: mixed,
 *                 pin-sha256?: mixed,
 *                 md5?: mixed,
 *             },
 *             crypto_method?: scalar|null, // The minimum version of TLS to accept; must be one of STREAM_CRYPTO_METHOD_TLSv*_CLIENT constants.
 *             extra?: array<string, mixed>,
 *             rate_limiter?: scalar|null, // Rate limiter name to use for throttling requests. // Default: null
 *             caching?: bool|array{ // Caching configuration.
 *                 enabled?: bool, // Default: false
 *                 cache_pool?: string, // The taggable cache pool to use for storing the responses. // Default: "cache.http_client"
 *                 shared?: bool, // Indicates whether the cache is shared (public) or private. // Default: true
 *                 max_ttl?: int, // The maximum TTL (in seconds) allowed for cached responses. Null means no cap. // Default: null
 *             },
 *             retry_failed?: bool|array{
 *                 enabled?: bool, // Default: false
 *                 retry_strategy?: scalar|null, // service id to override the retry strategy. // Default: null
 *                 http_codes?: array<string, array{ // Default: []
 *                     code?: int,
 *                     methods?: list<string>,
 *                 }>,
 *                 max_retries?: int, // Default: 3
 *                 delay?: int, // Time in ms to delay (or the initial value when multiplier is used). // Default: 1000
 *                 multiplier?: float, // If greater than 1, delay will grow exponentially for each retry: delay * (multiple ^ retries). // Default: 2
 *                 max_delay?: int, // Max time in ms that a retry should ever be delayed (0 = infinite). // Default: 0
 *                 jitter?: float, // Randomness in percent (between 0 and 1) to apply to the delay. // Default: 0.1
 *             },
 *         }>,
 *     },
 *     mailer?: bool|array{ // Mailer configuration
 *         enabled?: bool, // Default: false
 *         message_bus?: scalar|null, // The message bus to use. Defaults to the default bus if the Messenger component is installed. // Default: null
 *         dsn?: scalar|null, // Default: null
 *         transports?: array<string, scalar|null>,
 *         envelope?: array{ // Mailer Envelope configuration
 *             sender?: scalar|null,
 *             recipients?: list<scalar|null>,
 *             allowed_recipients?: list<scalar|null>,
 *         },
 *         headers?: array<string, string|array{ // Default: []
 *             value?: mixed,
 *         }>,
 *         dkim_signer?: bool|array{ // DKIM signer configuration
 *             enabled?: bool, // Default: false
 *             key?: scalar|null, // Key content, or path to key (in PEM format with the `file://` prefix) // Default: ""
 *             domain?: scalar|null, // Default: ""
 *             select?: scalar|null, // Default: ""
 *             passphrase?: scalar|null, // The private key passphrase // Default: ""
 *             options?: array<string, mixed>,
 *         },
 *         smime_signer?: bool|array{ // S/MIME signer configuration
 *             enabled?: bool, // Default: false
 *             key?: scalar|null, // Path to key (in PEM format) // Default: ""
 *             certificate?: scalar|null, // Path to certificate (in PEM format without the `file://` prefix) // Default: ""
 *             passphrase?: scalar|null, // The private key passphrase // Default: null
 *             extra_certificates?: scalar|null, // Default: null
 *             sign_options?: int, // Default: null
 *         },
 *         smime_encrypter?: bool|array{ // S/MIME encrypter configuration
 *             enabled?: bool, // Default: false
 *             repository?: scalar|null, // S/MIME certificate repository service. This service shall implement the `Symfony\Component\Mailer\EventListener\SmimeCertificateRepositoryInterface`. // Default: ""
 *             cipher?: int, // A set of algorithms used to encrypt the message // Default: null
 *         },
 *     },
 *     secrets?: bool|array{
 *         enabled?: bool, // Default: true
 *         vault_directory?: scalar|null, // Default: "%kernel.project_dir%/config/secrets/%kernel.runtime_environment%"
 *         local_dotenv_file?: scalar|null, // Default: "%kernel.project_dir%/.env.%kernel.runtime_environment%.local"
 *         decryption_env_var?: scalar|null, // Default: "base64:default::SYMFONY_DECRYPTION_SECRET"
 *     },
 *     notifier?: bool|array{ // Notifier configuration
 *         enabled?: bool, // Default: false
 *         message_bus?: scalar|null, // The message bus to use. Defaults to the default bus if the Messenger component is installed. // Default: null
 *         chatter_transports?: array<string, scalar|null>,
 *         texter_transports?: array<string, scalar|null>,
 *         notification_on_failed_messages?: bool, // Default: false
 *         channel_policy?: array<string, string|list<scalar|null>>,
 *         admin_recipients?: list<array{ // Default: []
 *             email?: scalar|null,
 *             phone?: scalar|null, // Default: ""
 *         }>,
 *     },
 *     rate_limiter?: bool|array{ // Rate limiter configuration
 *         enabled?: bool, // Default: false
 *         limiters?: array<string, array{ // Default: []
 *             lock_factory?: scalar|null, // The service ID of the lock factory used by this limiter (or null to disable locking). // Default: "auto"
 *             cache_pool?: scalar|null, // The cache pool to use for storing the current limiter state. // Default: "cache.rate_limiter"
 *             storage_service?: scalar|null, // The service ID of a custom storage implementation, this precedes any configured "cache_pool". // Default: null
 *             policy: "fixed_window"|"token_bucket"|"sliding_window"|"compound"|"no_limit", // The algorithm to be used by this limiter.
 *             limiters?: list<scalar|null>,
 *             limit?: int, // The maximum allowed hits in a fixed interval or burst.
 *             interval?: scalar|null, // Configures the fixed interval if "policy" is set to "fixed_window" or "sliding_window". The value must be a number followed by "second", "minute", "hour", "day", "week" or "month" (or their plural equivalent).
 *             rate?: array{ // Configures the fill rate if "policy" is set to "token_bucket".
 *                 interval?: scalar|null, // Configures the rate interval. The value must be a number followed by "second", "minute", "hour", "day", "week" or "month" (or their plural equivalent).
 *                 amount?: int, // Amount of tokens to add each interval. // Default: 1
 *             },
 *         }>,
 *     },
 *     uid?: bool|array{ // Uid configuration
 *         enabled?: bool, // Default: true
 *         default_uuid_version?: 7|6|4|1, // Default: 7
 *         name_based_uuid_version?: 5|3, // Default: 5
 *         name_based_uuid_namespace?: scalar|null,
 *         time_based_uuid_version?: 7|6|1, // Default: 7
 *         time_based_uuid_node?: scalar|null,
 *     },
 *     html_sanitizer?: bool|array{ // HtmlSanitizer configuration
 *         enabled?: bool, // Default: false
 *         sanitizers?: array<string, array{ // Default: []
 *             allow_safe_elements?: bool, // Allows "safe" elements and attributes. // Default: false
 *             allow_static_elements?: bool, // Allows all static elements and attributes from the W3C Sanitizer API standard. // Default: false
 *             allow_elements?: array<string, mixed>,
 *             block_elements?: list<string>,
 *             drop_elements?: list<string>,
 *             allow_attributes?: array<string, mixed>,
 *             drop_attributes?: array<string, mixed>,
 *             force_attributes?: array<string, array<string, string>>,
 *             force_https_urls?: bool, // Transforms URLs using the HTTP scheme to use the HTTPS scheme instead. // Default: false
 *             allowed_link_schemes?: list<string>,
 *             allowed_link_hosts?: list<string>|null,
 *             allow_relative_links?: bool, // Allows relative URLs to be used in links href attributes. // Default: false
 *             allowed_media_schemes?: list<string>,
 *             allowed_media_hosts?: list<string>|null,
 *             allow_relative_medias?: bool, // Allows relative URLs to be used in media source attributes (img, audio, video, ...). // Default: false
 *             with_attribute_sanitizers?: list<string>,
 *             without_attribute_sanitizers?: list<string>,
 *             max_input_length?: int, // The maximum length allowed for the sanitized input. // Default: 0
 *         }>,
 *     },
 *     webhook?: bool|array{ // Webhook configuration
 *         enabled?: bool, // Default: false
 *         message_bus?: scalar|null, // The message bus to use. // Default: "messenger.default_bus"
 *         routing?: array<string, array{ // Default: []
 *             service: scalar|null,
 *             secret?: scalar|null, // Default: ""
 *         }>,
 *     },
 *     remote-event?: bool|array{ // RemoteEvent configuration
 *         enabled?: bool, // Default: false
 *     },
 *     json_streamer?: bool|array{ // JSON streamer configuration
 *         enabled?: bool, // Default: false
 *     },
 * }
 * @psalm-type MonologConfig = array{
 *     use_microseconds?: scalar|null, // Default: true
 *     channels?: list<scalar|null>,
 *     handlers?: array<string, array{ // Default: []
 *         type: scalar|null,
 *         id?: scalar|null,
 *         enabled?: bool, // Default: true
 *         priority?: scalar|null, // Default: 0
 *         level?: scalar|null, // Default: "DEBUG"
 *         bubble?: bool, // Default: true
 *         interactive_only?: bool, // Default: false
 *         app_name?: scalar|null, // Default: null
 *         include_stacktraces?: bool, // Default: false
 *         process_psr_3_messages?: array{
 *             enabled?: bool|null, // Default: null
 *             date_format?: scalar|null,
 *             remove_used_context_fields?: bool,
 *         },
 *         path?: scalar|null, // Default: "%kernel.logs_dir%/%kernel.environment%.log"
 *         file_permission?: scalar|null, // Default: null
 *         use_locking?: bool, // Default: false
 *         filename_format?: scalar|null, // Default: "{filename}-{date}"
 *         date_format?: scalar|null, // Default: "Y-m-d"
 *         ident?: scalar|null, // Default: false
 *         logopts?: scalar|null, // Default: 1
 *         facility?: scalar|null, // Default: "user"
 *         max_files?: scalar|null, // Default: 0
 *         action_level?: scalar|null, // Default: "WARNING"
 *         activation_strategy?: scalar|null, // Default: null
 *         stop_buffering?: bool, // Default: true
 *         passthru_level?: scalar|null, // Default: null
 *         excluded_http_codes?: list<array{ // Default: []
 *             code?: scalar|null,
 *             urls?: list<scalar|null>,
 *         }>,
 *         accepted_levels?: list<scalar|null>,
 *         min_level?: scalar|null, // Default: "DEBUG"
 *         max_level?: scalar|null, // Default: "EMERGENCY"
 *         buffer_size?: scalar|null, // Default: 0
 *         flush_on_overflow?: bool, // Default: false
 *         handler?: scalar|null,
 *         url?: scalar|null,
 *         exchange?: scalar|null,
 *         exchange_name?: scalar|null, // Default: "log"
 *         channel?: scalar|null, // Default: null
 *         bot_name?: scalar|null, // Default: "Monolog"
 *         use_attachment?: scalar|null, // Default: true
 *         use_short_attachment?: scalar|null, // Default: false
 *         include_extra?: scalar|null, // Default: false
 *         icon_emoji?: scalar|null, // Default: null
 *         webhook_url?: scalar|null,
 *         exclude_fields?: list<scalar|null>,
 *         token?: scalar|null,
 *         region?: scalar|null,
 *         source?: scalar|null,
 *         use_ssl?: bool, // Default: true
 *         user?: mixed,
 *         title?: scalar|null, // Default: null
 *         host?: scalar|null, // Default: null
 *         port?: scalar|null, // Default: 514
 *         config?: list<scalar|null>,
 *         members?: list<scalar|null>,
 *         connection_string?: scalar|null,
 *         timeout?: scalar|null,
 *         time?: scalar|null, // Default: 60
 *         deduplication_level?: scalar|null, // Default: 400
 *         store?: scalar|null, // Default: null
 *         connection_timeout?: scalar|null,
 *         persistent?: bool,
 *         message_type?: scalar|null, // Default: 0
 *         parse_mode?: scalar|null, // Default: null
 *         disable_webpage_preview?: bool|null, // Default: null
 *         disable_notification?: bool|null, // Default: null
 *         split_long_messages?: bool, // Default: false
 *         delay_between_messages?: bool, // Default: false
 *         topic?: int, // Default: null
 *         factor?: int, // Default: 1
 *         tags?: list<scalar|null>,
 *         console_formatter_options?: mixed, // Default: []
 *         formatter?: scalar|null,
 *         nested?: bool, // Default: false
 *         publisher?: string|array{
 *             id?: scalar|null,
 *             hostname?: scalar|null,
 *             port?: scalar|null, // Default: 12201
 *             chunk_size?: scalar|null, // Default: 1420
 *             encoder?: "json"|"compressed_json",
 *         },
 *         mongodb?: string|array{
 *             id?: scalar|null, // ID of a MongoDB\Client service
 *             uri?: scalar|null,
 *             username?: scalar|null,
 *             password?: scalar|null,
 *             database?: scalar|null, // Default: "monolog"
 *             collection?: scalar|null, // Default: "logs"
 *         },
 *         elasticsearch?: string|array{
 *             id?: scalar|null,
 *             hosts?: list<scalar|null>,
 *             host?: scalar|null,
 *             port?: scalar|null, // Default: 9200
 *             transport?: scalar|null, // Default: "Http"
 *             user?: scalar|null, // Default: null
 *             password?: scalar|null, // Default: null
 *         },
 *         index?: scalar|null, // Default: "monolog"
 *         document_type?: scalar|null, // Default: "logs"
 *         ignore_error?: scalar|null, // Default: false
 *         redis?: string|array{
 *             id?: scalar|null,
 *             host?: scalar|null,
 *             password?: scalar|null, // Default: null
 *             port?: scalar|null, // Default: 6379
 *             database?: scalar|null, // Default: 0
 *             key_name?: scalar|null, // Default: "monolog_redis"
 *         },
 *         predis?: string|array{
 *             id?: scalar|null,
 *             host?: scalar|null,
 *         },
 *         from_email?: scalar|null,
 *         to_email?: list<scalar|null>,
 *         subject?: scalar|null,
 *         content_type?: scalar|null, // Default: null
 *         headers?: list<scalar|null>,
 *         mailer?: scalar|null, // Default: null
 *         email_prototype?: string|array{
 *             id: scalar|null,
 *             method?: scalar|null, // Default: null
 *         },
 *         verbosity_levels?: array{
 *             VERBOSITY_QUIET?: scalar|null, // Default: "ERROR"
 *             VERBOSITY_NORMAL?: scalar|null, // Default: "WARNING"
 *             VERBOSITY_VERBOSE?: scalar|null, // Default: "NOTICE"
 *             VERBOSITY_VERY_VERBOSE?: scalar|null, // Default: "INFO"
 *             VERBOSITY_DEBUG?: scalar|null, // Default: "DEBUG"
 *         },
 *         channels?: string|array{
 *             type?: scalar|null,
 *             elements?: list<scalar|null>,
 *         },
 *     }>,
 * }
 * @psalm-type TwigConfig = array{
 *     form_themes?: list<scalar|null>,
 *     globals?: array<string, array{ // Default: []
 *         id?: scalar|null,
 *         type?: scalar|null,
 *         value?: mixed,
 *     }>,
 *     autoescape_service?: scalar|null, // Default: null
 *     autoescape_service_method?: scalar|null, // Default: null
 *     cache?: scalar|null, // Default: true
 *     charset?: scalar|null, // Default: "%kernel.charset%"
 *     debug?: bool, // Default: "%kernel.debug%"
 *     strict_variables?: bool, // Default: "%kernel.debug%"
 *     auto_reload?: scalar|null,
 *     optimizations?: int,
 *     default_path?: scalar|null, // The default path used to load templates. // Default: "%kernel.project_dir%/templates"
 *     file_name_pattern?: list<scalar|null>,
 *     paths?: array<string, mixed>,
 *     date?: array{ // The default format options used by the date filter.
 *         format?: scalar|null, // Default: "F j, Y H:i"
 *         interval_format?: scalar|null, // Default: "%d days"
 *         timezone?: scalar|null, // The timezone used when formatting dates, when set to null, the timezone returned by date_default_timezone_get() is used. // Default: null
 *     },
 *     number_format?: array{ // The default format options for the number_format filter.
 *         decimals?: int, // Default: 0
 *         decimal_point?: scalar|null, // Default: "."
 *         thousands_separator?: scalar|null, // Default: ","
 *     },
 *     mailer?: array{
 *         html_to_text_converter?: scalar|null, // A service implementing the "Symfony\Component\Mime\HtmlToTextConverter\HtmlToTextConverterInterface". // Default: null
 *     },
 * }
 * @psalm-type WebProfilerConfig = array{
 *     toolbar?: bool|array{ // Profiler toolbar configuration
 *         enabled?: bool, // Default: false
 *         ajax_replace?: bool, // Replace toolbar on AJAX requests // Default: false
 *     },
 *     intercept_redirects?: bool, // Default: false
 *     excluded_ajax_paths?: scalar|null, // Default: "^/((index|app(_[\\w]+)?)\\.php/)?_wdt"
 * }
 * @psalm-type UxIconsConfig = array{
 *     icon_dir?: scalar|null, // The local directory where icons are stored. // Default: "%kernel.project_dir%/assets/icons"
 *     default_icon_attributes?: mixed, // Default attributes to add to all icons. // Default: {"fill":"currentColor"}
 *     icon_sets?: array<string, array{ // the icon set prefix (e.g. "acme") // Default: []
 *         path?: scalar|null, // The local icon set directory path. (cannot be used with 'alias')
 *         alias?: scalar|null, // The remote icon set identifier. (cannot be used with 'path')
 *         icon_attributes?: list<mixed>,
 *     }>,
 *     aliases?: list<scalar|null>,
 *     iconify?: bool|array{ // Configuration for the remote icon service.
 *         enabled?: bool, // Default: true
 *         on_demand?: bool, // Whether to download icons "on demand". // Default: true
 *         endpoint?: scalar|null, // The endpoint for the Iconify icons API. // Default: "https://api.iconify.design"
 *     },
 *     ignore_not_found?: bool, // Ignore error when an icon is not found. Set to 'true' to fail silently. // Default: false
 * }
 * @psalm-type LiveComponentConfig = array{
 *     secret?: scalar|null, // The secret used to compute fingerprints and checksums // Default: "%kernel.secret%"
 * }
 * @psalm-type StimulusConfig = array{
 *     controller_paths?: list<scalar|null>,
 *     controllers_json?: scalar|null, // Default: "%kernel.project_dir%/assets/controllers.json"
 * }
 * @psalm-type TurboConfig = array{
 *     broadcast?: bool|array{
 *         enabled?: bool, // Default: true
 *         entity_template_prefixes?: list<scalar|null>,
 *         doctrine_orm?: bool|array{ // Enable the Doctrine ORM integration
 *             enabled?: bool, // Default: false
 *         },
 *     },
 *     default_transport?: scalar|null, // Default: "default"
 * }
 * @psalm-type TwigComponentConfig = array{
 *     defaults?: array<string, string|array{ // Default: ["__deprecated__use_old_naming_behavior"]
 *         template_directory?: scalar|null, // Default: "components"
 *         name_prefix?: scalar|null, // Default: ""
 *     }>,
 *     anonymous_template_directory?: scalar|null, // Defaults to `components`
 *     profiler?: bool, // Enables the profiler for Twig Component (in debug mode) // Default: "%kernel.debug%"
 *     controllers_json?: scalar|null, // Deprecated: The "twig_component.controllers_json" config option is deprecated, and will be removed in 3.0. // Default: null
 * }
 * @psalm-type TwigExtraConfig = array{
 *     cache?: bool|array{
 *         enabled?: bool, // Default: false
 *     },
 *     html?: bool|array{
 *         enabled?: bool, // Default: false
 *     },
 *     markdown?: bool|array{
 *         enabled?: bool, // Default: true
 *     },
 *     intl?: bool|array{
 *         enabled?: bool, // Default: false
 *     },
 *     cssinliner?: bool|array{
 *         enabled?: bool, // Default: false
 *     },
 *     inky?: bool|array{
 *         enabled?: bool, // Default: false
 *     },
 *     string?: bool|array{
 *         enabled?: bool, // Default: false
 *     },
 *     commonmark?: array{
 *         renderer?: array{ // Array of options for rendering HTML.
 *             block_separator?: scalar|null,
 *             inner_separator?: scalar|null,
 *             soft_break?: scalar|null,
 *         },
 *         html_input?: "strip"|"allow"|"escape", // How to handle HTML input.
 *         allow_unsafe_links?: bool, // Remove risky link and image URLs by setting this to false. // Default: true
 *         max_nesting_level?: int, // The maximum nesting level for blocks. // Default: 9223372036854775807
 *         max_delimiters_per_line?: int, // The maximum number of strong/emphasis delimiters per line. // Default: 9223372036854775807
 *         slug_normalizer?: array{ // Array of options for configuring how URL-safe slugs are created.
 *             instance?: mixed,
 *             max_length?: int, // Default: 255
 *             unique?: mixed,
 *         },
 *         commonmark?: array{ // Array of options for configuring the CommonMark core extension.
 *             enable_em?: bool, // Default: true
 *             enable_strong?: bool, // Default: true
 *             use_asterisk?: bool, // Default: true
 *             use_underscore?: bool, // Default: true
 *             unordered_list_markers?: list<scalar|null>,
 *         },
 *         ...<mixed>
 *     },
 * }
 * @psalm-type ConfigType = array{
 *     imports?: ImportsConfig,
 *     parameters?: ParametersConfig,
 *     services?: ServicesConfig,
 *     ai?: AiConfig,
 *     mcp?: McpConfig,
 *     framework?: FrameworkConfig,
 *     monolog?: MonologConfig,
 *     twig?: TwigConfig,
 *     ux_icons?: UxIconsConfig,
 *     live_component?: LiveComponentConfig,
 *     stimulus?: StimulusConfig,
 *     turbo?: TurboConfig,
 *     twig_component?: TwigComponentConfig,
 *     twig_extra?: TwigExtraConfig,
 *     "when@dev"?: array{
 *         imports?: ImportsConfig,
 *         parameters?: ParametersConfig,
 *         services?: ServicesConfig,
 *         ai?: AiConfig,
 *         mcp?: McpConfig,
 *         debug?: DebugConfig,
 *         framework?: FrameworkConfig,
 *         monolog?: MonologConfig,
 *         twig?: TwigConfig,
 *         web_profiler?: WebProfilerConfig,
 *         ux_icons?: UxIconsConfig,
 *         live_component?: LiveComponentConfig,
 *         stimulus?: StimulusConfig,
 *         turbo?: TurboConfig,
 *         twig_component?: TwigComponentConfig,
 *         twig_extra?: TwigExtraConfig,
 *     },
 *     "when@prod"?: array{
 *         imports?: ImportsConfig,
 *         parameters?: ParametersConfig,
 *         services?: ServicesConfig,
 *         ai?: AiConfig,
 *         mcp?: McpConfig,
 *         framework?: FrameworkConfig,
 *         monolog?: MonologConfig,
 *         twig?: TwigConfig,
 *         ux_icons?: UxIconsConfig,
 *         live_component?: LiveComponentConfig,
 *         stimulus?: StimulusConfig,
 *         turbo?: TurboConfig,
 *         twig_component?: TwigComponentConfig,
 *         twig_extra?: TwigExtraConfig,
 *     },
 *     "when@test"?: array{
 *         imports?: ImportsConfig,
 *         parameters?: ParametersConfig,
 *         services?: ServicesConfig,
 *         ai?: AiConfig,
 *         mcp?: McpConfig,
 *         framework?: FrameworkConfig,
 *         monolog?: MonologConfig,
 *         twig?: TwigConfig,
 *         web_profiler?: WebProfilerConfig,
 *         ux_icons?: UxIconsConfig,
 *         live_component?: LiveComponentConfig,
 *         stimulus?: StimulusConfig,
 *         turbo?: TurboConfig,
 *         twig_component?: TwigComponentConfig,
 *         twig_extra?: TwigExtraConfig,
 *     },
 *     ...<string, ExtensionType|array{ // extra keys must follow the when@%env% pattern or match an extension alias
 *         imports?: ImportsConfig,
 *         parameters?: ParametersConfig,
 *         services?: ServicesConfig,
 *         ...<string, ExtensionType>,
 *     }>
 * }
 */
final class App
{
    /**
     * @param ConfigType $config
     *
     * @psalm-return ConfigType
     */
    public static function config(array $config): array
    {
        return AppReference::config($config);
    }
}

namespace Symfony\Component\Routing\Loader\Configurator;

/**
 * This class provides array-shapes for configuring the routes of an application.
 *
 * Example:
 *
 *     ```php
 *     // config/routes.php
 *     namespace Symfony\Component\Routing\Loader\Configurator;
 *
 *     return Routes::config([
 *         'controllers' => [
 *             'resource' => 'routing.controllers',
 *         ],
 *     ]);
 *     ```
 *
 * @psalm-type RouteConfig = array{
 *     path: string|array<string,string>,
 *     controller?: string,
 *     methods?: string|list<string>,
 *     requirements?: array<string,string>,
 *     defaults?: array<string,mixed>,
 *     options?: array<string,mixed>,
 *     host?: string|array<string,string>,
 *     schemes?: string|list<string>,
 *     condition?: string,
 *     locale?: string,
 *     format?: string,
 *     utf8?: bool,
 *     stateless?: bool,
 * }
 * @psalm-type ImportConfig = array{
 *     resource: string,
 *     type?: string,
 *     exclude?: string|list<string>,
 *     prefix?: string|array<string,string>,
 *     name_prefix?: string,
 *     trailing_slash_on_root?: bool,
 *     controller?: string,
 *     methods?: string|list<string>,
 *     requirements?: array<string,string>,
 *     defaults?: array<string,mixed>,
 *     options?: array<string,mixed>,
 *     host?: string|array<string,string>,
 *     schemes?: string|list<string>,
 *     condition?: string,
 *     locale?: string,
 *     format?: string,
 *     utf8?: bool,
 *     stateless?: bool,
 * }
 * @psalm-type AliasConfig = array{
 *     alias: string,
 *     deprecated?: array{package:string, version:string, message?:string},
 * }
 * @psalm-type RoutesConfig = array{
 *     "when@dev"?: array<string, RouteConfig|ImportConfig|AliasConfig>,
 *     "when@prod"?: array<string, RouteConfig|ImportConfig|AliasConfig>,
 *     "when@test"?: array<string, RouteConfig|ImportConfig|AliasConfig>,
 *     ...<string, RouteConfig|ImportConfig|AliasConfig>
 * }
 */
final class Routes
{
    /**
     * @param RoutesConfig $config
     *
     * @psalm-return RoutesConfig
     */
    public static function config(array $config): array
    {
        return $config;
    }
}
