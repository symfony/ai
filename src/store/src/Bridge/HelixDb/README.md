HelixDB Store
=============

Provides [HelixDB](https://github.com/HelixDB/helix-db) vector store integration for
Symfony AI Store.

HelixDB is an open-source graph-vector database. Unlike most stores, it does not expose a
generic REST API: every query must be authored in HelixQL, compiled, and deployed to the
running instance, after which it becomes an HTTP endpoint. This bridge therefore relies on a
fixed set of named queries that **must be deployed before the store can be used**.

Deploying the HelixQL queries
-----------------------------

This package ships the canonical HelixQL schema and queries in the `Resources/` directory:

 * `Resources/schema.hx` — the `Document` vector node definition
 * `Resources/queries.hx` — the `addDocument`, `searchDocuments`, `removeDocument` and
   `dropDocuments` queries used by the bridge

Copy both files into a HelixDB project and deploy them with the `helix` CLI before using
the store:

```bash
helix compile
helix deploy
```

> [!NOTE]
> HelixQL is a young, fast-moving language with no stable published grammar. The shipped
> `.hx` files are provided as a starting point and may need to be adjusted to the HelixQL
> syntax of your HelixDB version. Validate them with `helix compile` before deploying.

Usage
-----

```php
use Symfony\AI\Store\Bridge\HelixDb\Store;
use Symfony\Component\HttpClient\HttpClient;

$store = new Store(HttpClient::create(), 'http://127.0.0.1:6969');

// Verifies the canonical HelixQL queries are deployed and the instance is reachable.
$store->setup();
```

Because the schema is compiled into the deployed build, `setup()` does **not** create a
schema. It performs a health check that verifies the canonical queries are deployed.

License
-------

This bridge is released under the MIT license. The HelixDB server itself is licensed under
the AGPL-3.0 license; operating it is subject to that license.

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/ai/issues) and
   [send Pull Requests](https://github.com/symfony/ai/pulls)
   in the [main Symfony AI repository](https://github.com/symfony/ai)
