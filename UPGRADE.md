# Upgrading from v1 to v2

Most applications need one change: moving custom commands off the config file.

## 1. Move `custom_commands` onto the plugin

Closures in a config file break `php artisan config:cache` — Laravel serializes
config with `var_export()`, and closures cannot survive it. If you never ran
`config:cache` in production you may not have noticed; if you did, this was a
fatal error on deploy.

```diff
  // config/command-palette.php
- 'custom_commands' => [
-     fn () => [
-         CommandItem::make('My Action', '/my-url', 'Custom'),
-     ],
- ],
+ 'custom_commands' => [],
```

```diff
  FilamentCommandPalettePlugin::make()
+     ->commands(fn () => [
+         CommandItem::make('My Action', '/my-url', 'Custom'),
+     ])
```

The config key still works so nothing breaks on upgrade, but it is deprecated
and will be removed in v3.

## 2. `CommandItem` constructor changed

`CommandItem::make()` is unchanged and remains the supported way to build one.
Direct `new CommandItem(...)` calls need updating — the constructor now takes
only a label, and everything else is set fluently.

```diff
- new CommandItem($id, $label, $url, $group, $icon)
+ CommandItem::make($label, $url, $group, $icon)->id($id)
```

The `$id` property is now generated lazily via `getId()` rather than being a
public property set in the constructor. Ids also changed format, which only
matters if you persisted them somewhere.

## 3. Removed classes

| v1 | v2 |
| --- | --- |
| `Support\Commands\NavigationCommands` | `Support\Providers\NavigationCommandProvider`, `PageCommandProvider`, `ResourceCommandProvider` |
| `CommandRegistry::getCommands()` | `CommandRegistry::all()` |
| `CommandRegistry::getAllCommands()` | `CommandRegistry::all()` |

## 4. Published views must be republished

The templates were rewritten: inline styles moved into CSS classes, the Alpine
component was extracted into a shared partial, and the markup gained combobox
ARIA attributes. Published v1 views will not work against the v2 component.

```bash
php artisan command-palette:publish-views --force
```

If you customised them, diff your copies against the new ones rather than
overwriting blindly. Check first whether the CSS custom properties on
`.fi-cp-root` can do what you needed the fork for.

## 5. Expect fewer commands than before

This is the point of the release, but it can look like a regression.

v1 listed every registered page and resource regardless of policy, so users saw
entries they could not open. v2 checks `canAccess()`, `canViewAny()` and
`canCreate()`. If commands have disappeared for a user, they were 403s before.

If a command you expect is missing, check the policy first, then whether the
resource is tenant-scoped with no tenant in scope.

## 6. The publish-views page is now local-only

`PublishCommandPaletteViewsPage::canAccess()` returns false outside the `local`
environment. Writing to the application's view directory from a production admin
panel was not something to leave switched on by default. Override `canAccess()`
on a subclass if you need it elsewhere.

## New in v2

Nothing below is required, but this is what the upgrade buys:

- Record search (`->recordSearch()`), on by default, using each resource's
  existing global search configuration
- Actions via `->action()` and browser events via `->dispatch()`
- Fuzzy matching with highlighting, and frecency-ranked recents
- Per-panel configuration through the fluent plugin API
- Full translations (`--tag=command-palette-translations`)
- `CommandPaletteOpened` and `CommandExecuted` events
- Lazy loading — commands are no longer embedded in every page response
