# CHANGELOG

## 0.1.0

**Initial release**

- Introduced `PromptTemplate` for prompt template management
- Added `StringRenderer` for simple {variable} replacement (zero dependencies)
- Added `ExpressionRenderer` for Symfony Expression Language integration
- Implemented extensible renderer strategy pattern via `RendererInterface`
- Added comprehensive exception hierarchy
- Provides static factory methods for convenient template creation
