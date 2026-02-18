<?php

use Usamamuneerchaudhary\CommandPalette\Support\CommandItem;

return [
    /*
    |--------------------------------------------------------------------------
    | Key Bindings
    |--------------------------------------------------------------------------
    | Keyboard shortcut to open the command palette.
    | Use 'mod' for CMD on Mac and Ctrl on Windows/Linux.
    | When using Filament global search with a shortcut too, use different
    | keys to avoid conflict (e.g. panel->globalSearchKeyBindings(['mod+shift+k'])).
    */
    'key_bindings' => [
        'mod+k',
    ],

    /*
    |--------------------------------------------------------------------------
    | Show Topbar Button
    |--------------------------------------------------------------------------
    | Whether to show an optional trigger button in the topbar.
    */
    'show_topbar_button' => true,

    /*
    |--------------------------------------------------------------------------
    | Show Topbar Button When Global Search Enabled
    |--------------------------------------------------------------------------
    | When Filament global search is enabled, the command palette topbar
    | button is hidden by default to avoid two search-style controls. Set to
    | true if you want both visible (consider using different key bindings
    | for each, e.g. globalSearchKeyBindings(['mod+shift+k']) on the panel).
    */
    'show_topbar_button_when_global_search_enabled' => false,

    /*
    |--------------------------------------------------------------------------
    | Max Results
    |--------------------------------------------------------------------------
    | Maximum number of results to display in the command palette.
    */
    'max_results' => 10,

    /*
    |--------------------------------------------------------------------------
    | Custom Commands
    |--------------------------------------------------------------------------
    | Additional commands to include. Each closure should return an array of
    | Usamamuneerchaudhary\CommandPalette\Support\CommandItem instances.
    |
    | Example:
    | 'custom_commands' => [
    | fn () => [
    |        CommandItem::make('My Action', '/some-url')->group('Custom'),
    |        CommandItem::make('Other', '/other-url', 'Custom'), // 3rd param also works
    |    ],
    | ],
    */
    'custom_commands' => [
        //
    ],
];
