# Filament Command Palette

[![Latest Version on Packagist](https://img.shields.io/packagist/v/usamamuneerchaudhary/filament-command-palette?style=flat-square&g)](https://packagist.org/packages/usamamuneerchaudhary/filament-command-palette)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/usamamuneerchaudhary/filament-command-palette/badges/quality-score.png?b=main)](https://scrutinizer-ci.com/g/usamamuneerchaudhary/filament-command-palette/?branch=main)
[![CodeFactor](https://www.codefactor.io/repository/github/usamamuneerchaudhary/filament-command-palette/badge)](https://www.codefactor.io/repository/github/usamamuneerchaudhary/filament-command-palette)
[![Build Status](https://scrutinizer-ci.com/g/usamamuneerchaudhary/filament-command-palette/badges/build.png?b=main)](https://scrutinizer-ci.com/g/usamamuneerchaudhary/filament-command-palette/build-status/main)
[![Code Intelligence Status](https://scrutinizer-ci.com/g/usamamuneerchaudhary/filament-command-palette/badges/code-intelligence.svg?b=main)](https://scrutinizer-ci.com/code-intelligence)
[![Total Downloads](https://img.shields.io/packagist/dt/usamamuneerchaudhary/filament-command-palette?style=flat-square)](https://packagist.org/packages/usamamuneerchaudhary/filament-command-palette)
[![Licence](https://img.shields.io/packagist/l/usamamuneerchaudhary/filament-command-palette?style=flat-square)](https://github.com/usamamuneerchaudhary/filament-command-palette/blob/HEAD/LICENSE.md)

![Filament Command Palette](public/gifs/filament-command-palatte.gif)

A Spotlight/CMD+K style command palette for quick navigation and actions across Filament panels.

## Features

- **Keyboard shortcut**: Press `Cmd+K` (Mac) or `Ctrl+K` (Windows/Linux) to open
- **Quick navigation**: Jump to any page, resource, or navigation item
- **Search**: Filter commands by typing
- **Keyboard navigation**: Use arrow keys and Enter to select
- **Optional topbar button**: Click to open from the topbar

## Installation

```bash
composer require usamamuneerchaudhary/filament-command-palette
```

## Setup

Register the plugin in your Filament panel provider:

```php
use Usamamuneerchaudhary\CommandPalette\FilamentCommandPalettePlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            FilamentCommandPalettePlugin::make(),
            // ...
        ]);
}
```

## Configuration

Publish the config file (optional):

```bash
php artisan vendor:publish --tag=command-palette-config
```

Options in `config/command-palette.php`:

- `key_bindings`: Keyboard shortcuts (default: `['mod+k']`)
- `show_topbar_button`: Show optional trigger in topbar (default: `true`)
- `max_results`: Max results per category (default: `10`)
- `template`: Visual layout — `standard` (default), `groups`, or `preview`
- `templates`: Per-template empty-state and no-results copy
- `include_publish_views_command`: Show "Publish views" in the command palette (default: `true`)
- `custom_commands`: Array of closures returning `CommandItem[]` for extensibility

### Templates

Choose a layout via config:

```php
'template' => 'standard', // or 'groups' | 'preview'
```

| Template | Description |
|----------|-------------|
| `standard` | Compact Filament-styled modal with grouped results |
| `groups` | Wider empty-state defaults, distinct group headers, indigo selection |
| `preview` | Split list + detail preview pane on hover/keyboard selection |

## Publishing Views

You can publish the package views to customize the command palette layout, styling, and behavior. Published views go to `resources/views/vendor/command-palette/` (including `livewire/templates/` for each layout) and can be edited freely.

**From the command palette:** Open the palette (Cmd+K), search for "Publish views", and select it to open a page with a one-click publish button.

**From the terminal:**

```bash
php artisan command-palette:publish-views
```

Or using Laravel's vendor publish directly:

```bash
php artisan vendor:publish --tag=command-palette-views
```

Use `--force` to overwrite existing published views.

## Custom Commands

Add custom commands via config:

```php
'custom_commands' => [
    fn () => [
        \Usamamuneerchaudhary\CommandPalette\Support\CommandItem::make(
            'My Action',
            '/my-url',
            'Custom',
        ),
    ],
],
```

For the `preview` template, you can attach optional metadata:

```php
\Usamamuneerchaudhary\CommandPalette\Support\CommandItem::make('Edit User', '/admin/users/1/edit')
    ->group('Users')
    ->description('Administration')
    ->preview(['Email' => 'user@example.com', 'Role' => 'Admin']),
```

When `description` / `preview` are omitted, the preview pane falls back to the command group and URL.

## Requirements

- PHP 8.2+
- Filament v5
- Laravel 11 or 12

## Screenshots 
<p align="center">
  <img src="public/screenshots/command-palette.png" alt="Screenshot" width="800">
</p>


## License

MIT
