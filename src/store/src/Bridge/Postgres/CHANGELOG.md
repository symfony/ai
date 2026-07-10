CHANGELOG
=========

0.11
----

 * [BC BREAK] Quote table and column name
   > Quoting an identifier also makes it case-sensitive, whereas unquoted names are always folded to lower case. For example, the identifiers FOO, foo, and "foo" are considered the same by PostgreSQL, but "Foo" and "FOO" are different from these three and each other. (The folding of unquoted names to lower case in PostgreSQL is incompatible with the SQL standard, which says that unquoted names should be folded to upper case. Thus, foo should be equivalent to "FOO" not "foo" according to the standard. If you want to write portable applications you are advised to always quote a particular name or never quote it.)

0.9
---
 * Introduce `StoreFactory`
 * Introduce a `lang` argument to configure the lang used in embeddings

0.1
---

 * Add the bridge
