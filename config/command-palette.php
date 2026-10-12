<?php

use Usamamuneerchaudhary\CommandPalette\Support\CommandRegistry;

return [

    /*
    |--------------------------------------------------------------------------
    | Defaults, not the last word
    |--------------------------------------------------------------------------
    | Everything here is a default for every panel. To configure one panel
    | differently, set the same option on its plugin instance, which wins:
    |
    |     FilamentCommandPalettePlugin::make()
    |         ->keyBindings(['mod+shift+p'])
    |         ->template('groups')
    */

    /*
    |--------------------------------------------------------------------------
    | Key Bindings
    |--------------------------------------------------------------------------
    | Use 'mod' for Cmd on Mac and Ctrl elsewhere. If Filament's own global
    | search is enabled with a shortcut, give the two different keys — for
    | example globalSearchKeyBindings(['mod+shift+k']) on the panel.
    */
    'key_bindings' => [
        'mod+k',
    ],

    /*
    |--------------------------------------------------------------------------
    | Topbar Button
    |--------------------------------------------------------------------------
    | When Filament's global search is enabled the button is hidden by default,
    | because two search boxes side by side is one too many.
    */
    'show_topbar_button' => true,
    'show_topbar_button_when_global_search_enabled' => false,

    /*
    |--------------------------------------------------------------------------
    | Results
    |--------------------------------------------------------------------------
    | Total cap on the ranked list, not a per-category limit. Recents are
    | capped separately by recents.max. Record search uses
    | record_search.max_results.
    */
    'max_results' => 10,

    /*
    | Leave null to use the translated default.
    */
    'placeholder' => null,

    /*
    |--------------------------------------------------------------------------
    | Template
    |--------------------------------------------------------------------------
    | 'standard' | 'groups' | 'preview'
    */
    'template' => 'standard',

    'templates' => [
        'standard' => [
            'empty_title' => null,
            'empty_description' => null,
        ],
        'groups' => [
            'empty_title' => null,
            'empty_description' => null,
        ],
        'preview' => [
            'empty_heading' => null,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Record Search
    |--------------------------------------------------------------------------
    | Search actual records, not just pages. This reuses each resource's
    | existing global search setup — getGloballySearchableAttributes() and the
    | rest — so resources that already support global search need no extra work.
    |
    | Queries are debounced and run on the server, scoped by the same policies
    | Filament applies everywhere else.
    */
    'record_search' => [
        'enabled' => true,
        'max_results' => 15,
        'debounce' => 300,
        'min_characters' => 2,
    ],

    /*
    |--------------------------------------------------------------------------
    | Recent Commands
    |--------------------------------------------------------------------------
    | Recently used commands are ranked higher and shown first on an empty
    | search. Stored per panel in the browser, never on the server.
    */
    'recents' => [
        'enabled' => true,
        'max' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Search Modes
    |--------------------------------------------------------------------------
    | Prefixes that narrow the search: > actions, # records, ? help.
    */
    'modes' => [
        'enabled' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Built-in Commands
    |--------------------------------------------------------------------------
    | Turn individual built-ins off. 'panels' and 'tenants' are off by default
    | because they only make sense in multi-panel or multi-tenant apps.
    */
    'built_in_commands' => [
        'theme' => true,
        'profile' => true,
        'logout' => true,
        'panels' => false,
        'tenants' => false,
    ],

    /*
    | The publish-views page is registered when this is true, but canAccess()
    | is still local-only. Set false to hide it even in local development.
    */
    'include_publish_views_command' => true,

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    | Seconds to cache the built command list, keyed by panel, user, tenant and
    | locale. Zero rebuilds on every open, which is correct when navigation
    | changes with application state. Raise it if building the list is slow and
    | your navigation is static.
    */
    'cache' => [
        'ttl' => 0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Providers
    |--------------------------------------------------------------------------
    | The pipeline that produces commands. Reorder, remove, or add your own
    | class implementing the CommandProvider contract.
    */
    'providers' => CommandRegistry::DEFAULT_PROVIDERS,

    'search_providers' => CommandRegistry::DEFAULT_SEARCH_PROVIDERS,

    /*
    |--------------------------------------------------------------------------
    | Custom Commands (deprecated)
    |--------------------------------------------------------------------------
    | Kept working for v1 upgrades, but do not add closures here: config files
    | are serialized by `php artisan config:cache` and closures cannot survive
    | it — the command fails with "Your configuration files are not
    | serializable". Register commands on the plugin instead:
    |
    |     FilamentCommandPalettePlugin::make()
    |         ->commands(fn () => [
    |             CommandItem::make('Clear cache')
    |                 ->action(fn () => Artisan::call('cache:clear')),
    |         ])
    */
    'custom_commands' => [
        //
    ],

];
