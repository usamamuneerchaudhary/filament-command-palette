# Filament Command Palette

[![Latest Version on Packagist](https://img.shields.io/packagist/v/usamamuneerchaudhary/filament-command-palette?style=flat-square)](https://packagist.org/packages/usamamuneerchaudhary/filament-command-palette)
[![Tests](https://img.shields.io/github/actions/workflow/status/usamamuneerchaudhary/filament-command-palette/slipway.yml?branch=main&label=tests&style=flat-square)](https://github.com/usamamuneerchaudhary/filament-command-palette/actions/workflows/slipway.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/usamamuneerchaudhary/filament-command-palette?style=flat-square)](https://packagist.org/packages/usamamuneerchaudhary/filament-command-palette)
[![Daily Downloads](https://img.shields.io/packagist/dd/usamamuneerchaudhary/filament-command-palette?style=flat-square)](https://packagist.org/packages/usamamuneerchaudhary/filament-command-palette)
[![Licence](https://img.shields.io/packagist/l/usamamuneerchaudhary/filament-command-palette?style=flat-square)](LICENSE.md)

![Filament Command Palette](public/gifs/filament-command-palatte.gif)

Press `⌘K` and go anywhere in your panel — a page, a record, or an action — without touching the sidebar.

## Features

- **Fuzzy search.** `crus` finds "Create User". Matched characters are highlighted.
- **Record search.** Type an invoice number, land on the invoice. Built on each resource's existing Filament global search configuration, so most apps get this with no extra setup.
- **Actions, not just links.** Run a server closure or fire a browser event. Sign out, switch theme, clear a cache.
- **Authorization aware.** Commands are filtered by the same policies Filament uses, and every action is re-authorized on the server before it runs.
- **Learns what you use.** Recently run commands rank higher and appear first on an empty search.
- **Search modes.** `>` for actions, `#` for records, `?` for the shortcut list.
- **Keyboard and screen reader ready.** Full combobox semantics, focus trapping, focus restoration, and result announcements.
- **Per panel.** Every option can differ between panels in the same application.
- **Three layouts.** `standard`, `groups`, `preview`.

## Requirements

- PHP 8.3+
- Laravel 11, 12 or 13
- Filament v4 or v5

## Installation

```bash
composer require usamamuneerchaudhary/filament-command-palette
```

Register the plugin on your panel:

```php
use Usamamuneerchaudhary\CommandPalette\FilamentCommandPalettePlugin;

public function panel(Panel $panel): Panel
{
    return $panel->plugins([
        FilamentCommandPalettePlugin::make(),
    ]);
}
```

That is the whole setup. The palette picks up your navigation, pages and resources automatically.

Or let the installer register the plugin on the panels you choose:

```bash
php artisan command-palette:install
```

It discovers Filament panel providers, injects `FilamentCommandPalettePlugin::make()`, asks for a shortcut, and can publish config and translations. Providers that already register the plugin are left alone. Use `--no-interaction`, `--panel=`, `--shortcut=` and `--publish` in CI.

## Configuration

Options can be set on the plugin, per panel:

```php
FilamentCommandPalettePlugin::make()
    ->keyBindings(['mod+k'])
    ->template('groups')
    ->maxResults(12)
    ->recordSearch(true, maxResults: 20)
    ->recents(true, max: 5)
    ->topbarButton(false)
    ->cacheFor(60)
```

Or globally, in the published config file:

```bash
php artisan vendor:publish --tag=command-palette-config
```

Plugin options win over the config file, so the config sets your defaults and each panel adjusts from there.

`maxResults()` / `max_results` is a **total** cap on the ranked list, not a per-category limit. Recents are capped separately by `recents.max` (5 by default). Record search has its own `record_search.max_results`.

### Key bindings

`mod` means Cmd on macOS and Ctrl elsewhere. If Filament's own global search is bound to a shortcut, give the two different keys:

```php
$panel
    ->globalSearchKeyBindings(['mod+shift+k'])
    ->plugins([
        FilamentCommandPalettePlugin::make()->keyBindings(['mod+k']),
    ]);
```

## Custom commands

Register commands on the plugin:

```php
use Usamamuneerchaudhary\CommandPalette\Support\CommandItem;

FilamentCommandPalettePlugin::make()
    ->commands(fn () => [
        CommandItem::make('Billing portal', 'https://billing.example.com')
            ->group('External')
            ->openInNewTab()
            ->keywords(['invoice', 'subscription', 'payment']),
    ])
```

> Register commands here rather than in the `custom_commands` config key. Config files are serialized by `php artisan config:cache`, and closures cannot survive that — it fails with *"Your configuration files are not serializable"*. The config key still works, but the plugin is the right home for anything with a closure in it.

### Running an action

Return a string to redirect afterwards:

```php
CommandItem::make('Clear application cache')
    ->icon(Heroicon::OutlinedTrash)
    ->action(function () {
        Artisan::call('cache:clear');

        Notification::make()->title('Cache cleared')->success()->send();
    })
    ->authorize(fn () => auth()->user()->isAdmin())
```

The browser only ever sends the command's id. The closure, the URL and the authorization check are all resolved server-side from a freshly built registry, so a stale or tampered payload cannot run anything.

Invokable classes survive `config:cache` and keep the panel provider small:

```php
CommandItem::make('Clear application cache')
    ->action(ClearApplicationCache::class)
```

```php
class ClearApplicationCache
{
    public function __invoke(CommandItem $command): void
    {
        Artisan::call('cache:clear');
    }
}
```

### Page context

Implement `HasPaletteCommands` on a page or resource. Those commands appear in a **Current page** group and are not stored in the static command cache. Action closures may take the current record as a second argument; it is loaded through the resource query, never from a client-supplied model class.

```php
use Usamamuneerchaudhary\CommandPalette\Contracts\HasPaletteCommands;

class EditInvoice extends EditRecord implements HasPaletteCommands
{
    public static function getPaletteCommands(): iterable
    {
        return [
            CommandItem::make('Regenerate PDF')
                ->action(fn (CommandItem $command, Invoice $record) => $record->regeneratePdf()),
        ];
    }
}
```

### Nested commands

```php
CommandItem::make('Assign role')
    ->children([
        CommandItem::make('Admin')->action(fn () => $this->assign('admin')),
        CommandItem::make('Editor')->action(fn () => $this->assign('editor')),
    ])
```

Escape pops one level. Escape at the root still closes the palette.

### Opening a Filament action modal

The palette does not render the modal. After the command is authorized it asks the host page to `mountAction()`:

```php
CommandItem::make('Create user')->mountAction('create')
```

### Keyboard chords

Two-stroke shortcuts run the same authorized `runCommand()` path. They are ignored while typing in an input, textarea, select, contenteditable node, or Filament form field:

```php
CommandItem::make('Customers', CustomerResource::getUrl())->chord('g c')
```

### Firing a browser event

For anything that lives purely in the client:

```php
CommandItem::make('Open help')->dispatch('open-help-drawer', ['section' => 'billing'])
```

### Controlling visibility

```php
CommandItem::make('Impersonate user', '/impersonate')
    ->visible(fn () => config('app.debug'))
    ->authorize(fn () => auth()->user()->can('impersonate'))
```

Or gate everything at once:

```php
FilamentCommandPalettePlugin::make()
    ->authorize(fn (CommandItem $item) => ! str_starts_with($item->group, 'Internal'))
```

## Record search

Any resource that supports Filament's global search is searchable from the palette:

```php
class OrderResource extends Resource
{
    protected static ?string $recordTitleAttribute = 'reference';

    public static function getGloballySearchableAttributes(): array
    {
        return ['reference', 'customer.name'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Customer' => $record->customer->name,
            'Total' => $record->total_formatted,
        ];
    }
}
```

Queries are debounced, run on the server, and respect the same scoping and policies as everywhere else in Filament. Details show up in the `preview` template's side pane.

To turn it off: `->recordSearch(false)`.

`Htmlable` titles from `getGlobalSearchResultTitle()` render in the preview pane after sanitizing. Link-style `getGlobalSearchResultActions()` become buttons there; modal actions stay on the Filament page.

For Scout-backed models:

```php
FilamentCommandPalettePlugin::make()->scoutIntegration()
```

`laravel/scout` is optional. Models without `Searchable` keep using Eloquent. Scout returns keys; Filament still applies tenancy, policies, titles and URLs.

Cache keys include the panel, user, tenant, locale, and role/permission names when the user exposes them. Add extra material with `->cacheSignature(fn () => auth()->user()->team_id)`.

## Custom providers

The command list is produced by a pipeline. Add your own stage:

```php
use Usamamuneerchaudhary\CommandPalette\Contracts\CommandProvider;

class DocumentationCommands implements CommandProvider
{
    public function commands(Panel $panel): iterable
    {
        return collect(Documentation::published())->map(
            fn ($doc) => CommandItem::make($doc->title, $doc->url)->group('Docs')
        );
    }
}
```

```php
FilamentCommandPalettePlugin::make()
    ->providers([...CommandRegistry::DEFAULT_PROVIDERS, DocumentationCommands::class])
```

Implement `SearchesCommands` instead if your results depend on the search term.

## Events

```php
use Usamamuneerchaudhary\CommandPalette\Events\CommandExecuted;

Event::listen(CommandExecuted::class, function (CommandExecuted $event) {
    Log::info('palette', ['command' => $event->command['label'], 'query' => $event->query]);
});
```

`CommandPaletteOpened` fires the first time someone opens it in a page session. Between them you can find out which commands actually earn their place.

## Translations

```bash
php artisan vendor:publish --tag=command-palette-translations
```

Every string is translatable. Layouts use logical properties, so RTL panels lay out correctly without further work.

## Customising the views

```bash
php artisan command-palette:publish-views
```

The publish-views page is registered by default, but `canAccess()` is local-only, so it never appears outside `local`. Set `include_publish_views_command` to `false` to hide it entirely.

Most appearance changes do not need this — the palette is styled entirely through CSS custom properties:

```css
.fi-cp-root {
    --fi-cp-radius: 0.25rem;
    --fi-cp-selected-accent: rgb(var(--danger-600));
}
```

Published views take precedence over the package's from then on, which also means future package updates will not reach them. Prefer the variables where you can.

## Performance

The palette ships an empty shell with the page and fetches its commands the first time it opens. Nothing is embedded in every page response.

If building the list is expensive and your navigation is static, cache it:

```php
FilamentCommandPalettePlugin::make()->cacheFor(300)
```

Cache keys include the panel, user, tenant, locale and a hash of the user's roles and permissions, so nothing leaks between users. Context commands are never stored in that cache.

## Upgrading from v1

See [UPGRADE.md](UPGRADE.md). The short version: move `custom_commands` closures onto the plugin, and check any code that constructed `CommandItem` directly.

## Testing

```bash
composer test
composer analyse
composer format
composer slipway:compile   # regenerate .github/workflows/slipway.yml after changing slipway.php
```

## Credits

- [Usama Muneer](https://github.com/usamamuneerchaudhary)
- [All contributors](../../contributors)

## License

MIT. See [LICENSE.md](LICENSE.md).
