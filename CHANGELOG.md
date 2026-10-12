# Changelog

All notable changes to `filament-command-palette` will be documented in this file.

## 2.0

### Security

- Pages and resources are now filtered by `canAccess()`, `canViewAny()` and `canCreate()`. v1 advertised every registered page and resource to every authenticated user, which both produced 403s on selection and exposed the panel's structure.
- Actions are re-authorized on the server immediately before running. The client sends only a command id; URLs, closures and permissions are resolved from a freshly built registry.
- The Livewire `panelId` is locked. Commands, record search and actions resolve the current Filament panel and refuse users who fail `canAccessPanel()`.
- Panel-switch commands omit panels the user cannot access.
- Record search honours `min_characters`, caps query length, and is rate-limited per user.
- Result labels are HTML-escaped before match highlighting is applied.
- The publish-views page is restricted to the `local` environment. It stays registered by default (`include_publish_views_command`) because `canAccess()` is local-only.

### Fixed

- `custom_commands` closures in the config file broke `php artisan config:cache` with "Your configuration files are not serializable". Commands now register on the plugin; a test guards the shipped config against regressions. The config key still works but is deprecated.
- Nested navigation items deeper than one level were silently dropped; flattening is now properly recursive with a depth guard.
- Result rendering was quadratic in the number of results on every keystroke.
- Bare navigation items returned alongside groups were ignored.
- Tenant-scoped resource URLs could be guessed incorrectly; the current tenant is now passed explicitly. Resources whose `isScopedToTenant` default is true are still listed on panels that do not use tenancy.
- README requirements did not match the composer constraints.
- Selected-row styling now uses the shared `.is-selected` class instead of Filament gray utilities that did not follow dark mode.
- Same-origin result clicks and Enter follow the panel's `spa()` setting via `Alpine.navigate()`, so middle-click and cmd-click keep a real `href`.
- CSS loads on panels that register the plugin, not on every Filament panel in the app.

### Added

- Record search built on Filament's global search API, debounced and server-side.
- `CommandItem::action()` for server closures and `CommandItem::dispatch()` for browser events.
- `keywords()`, `visible()`, `authorize()`, `priority()`, `badge()`, `id()` on `CommandItem`.
- Fuzzy matching with match highlighting, replacing substring filtering.
- Frecency-ranked recent commands, stored per panel in the browser.
- Search modes: `>` actions, `#` records, `?` help.
- Fluent per-panel plugin API; two panels can now differ in every option.
- `CommandProvider` and `SearchesCommands` contracts, with a configurable pipeline.
- Built-in commands: theme switching, sign out, profile, panel and tenant switching.
- `CommandPaletteOpened` and `CommandExecuted` events. `CommandExecuted` carries a serializable command array and `userId`, not a `CommandItem` with closures.
- Full translation coverage and a `command-palette-translations` publish tag.
- Optional command list caching, keyed by panel, user, tenant and locale.
- CI: [Slipway](https://github.com/usamamuneerchaudhary/laravel-slipway) compiles GitHub Actions from `slipway.php` (PHP 8.3–8.5, Pint, Larastan, PHPUnit).

### Changed

- Commands load on first open instead of being base64-encoded into every page response.
- Icons are sent once by name rather than as pre-rendered SVG on every row.
- Templates rewritten around shared search, states and results partials: combobox ARIA, focus trapping, Home/End, a live region, and translated empty/placeholder copy.
- CSS rebuilt on custom properties, using Filament's `--primary-*` channels, with RTL logical properties, reduced-motion and forced-colors support.
- `CommandRegistry::getCommands()` and `getAllCommands()` replaced by `all()`, `search()`, `find()` and `payload()`.
- `CommandItem` constructor is now label-only; ids are generated.

### Removed

- `Support\Commands\NavigationCommands`, replaced by three focused providers.

## 1.4 - 2026-07-10

- Added `groups` and `preview` templates.

## 1.3 - 2026-07-07

- Added Laravel 13 support.

## 1.2 - 2026-02-18

- Version constraint cleanup.

## 1.1 - 2026-02-18

- Filament v4 support.

## 1.0 - 2026-02-18

- Initial release, including a command to publish the palette views.
