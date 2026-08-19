# Changelog

All notable changes to this module will be documented in this file.

## [1.1.0] - 2026-08-17

### Added

- Configurable node icons by resource class, resource template, or property value.
- Node icons in the graph legend and a pan/zoom-aware Sigma canvas layer.

### Fixed

- Property-value node color mappings now accept an actual property value instead of incorrectly displaying a second property selector.
- Property-value node icon mappings provide the same exact-value input.

## [1.0.1] - 2026-03-20

### Fixed

- **Security:** Escaped `$width` and `$height` block values in the view template to prevent XSS.
- **Security:** HTML-type metadata values are now sanitized through Omeka's HTML purifier instead of being passed raw.
- **Security:** URLs in metadata `href` attributes are now properly escaped.
- **Security:** Moved CDN script loading from `onBootstrap()` (every page) to `render()` (only pages with the block).
- **Error handling:** AJAX controller now logs errors via `Omeka\Logger` instead of silently swallowing exceptions.
- **Error handling:** View template now returns early when no query or no items are found, instead of continuing execution.

### Added

- Class-level docblocks on all classes.
- `@param`/`@return` docblocks on all public methods.


### Deprecated

- `functions.php` — use `\NodeGraph\Service\GraphHelper` instead.

## [1.0.0] - 2025-11-10

### Added

- Initial release.
- Interactive node graph visualization using sigma.js with WebGL rendering.
- Configurable item grouping by resource class, resource template, or property value.
- Custom node colors per group.
- Configurable relationship property selection.
- Optional exclusion of isolated nodes (no relationships).
- Background cache building for large datasets.
- Popup content with title, thumbnail, metadata, and relationships.
- Configurable graph width and height.
