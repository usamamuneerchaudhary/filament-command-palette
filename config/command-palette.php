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
    | Template
    |--------------------------------------------------------------------------
    | Visual layout for the command palette.
    | Options: 'standard' | 'groups' | 'preview'
    */
    'template' => 'standard',

    /*
    |--------------------------------------------------------------------------
    | Template Copy
    |--------------------------------------------------------------------------
    | Per-template empty-state and no-results messaging.
    */
    'templates' => [
        'standard' => [
            'empty_title' => null,
            'empty_description' => null,
        ],
        'groups' => [
            'empty_title' => 'Search commands and pages',
            'empty_description' => 'Quickly navigate your panel by running a global search.',
            'no_results_title' => 'No results found',
            'no_results_description' => 'We couldn\'t find anything with that term. Please try again.',
        ],
        'preview' => [
            'empty_heading' => 'Recent commands',
            'no_results_title' => 'No commands found',
            'no_results_description' => 'We couldn\'t find anything with that term. Please try again.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Include Publish Views Command
    |--------------------------------------------------------------------------
    | When true, adds a "Publish views" option to the command palette. Selecting
    | it opens a page where you can publish the Blade views for customization.
    */
    'include_publish_views_command' => true,

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
