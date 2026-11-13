<?php

declare(strict_types=1);

return [

    'placeholder' => 'Search for a page, record or action',

    'trigger' => [
        'label' => 'Search',
        'shortcut_mac' => '⌘K',
        'shortcut_other' => 'Ctrl+K',
    ],

    'groups' => [
        'navigation' => 'Navigation',
        'pages' => 'Pages',
        'resources' => 'Resources',
        'actions' => 'Actions',
        'records' => 'Records',
        'recents' => 'Recent',
        'pins' => 'Pinned',
        'command_palette' => 'Command palette',
        'current_page' => 'Current page',
    ],

    'commands' => [
        'list' => 'List :label',
        'create' => 'Create :label',
        'view' => 'View :label',
        'edit' => 'Edit :label',
        'delete' => 'Delete :label',
        'profile' => 'Open your profile',
        'logout' => 'Sign out',
        'switch_panel' => 'Switch to :panel',
        'switch_tenant' => 'Switch to :tenant',
        'switch_tenant_menu' => 'Switch tenant',
        'publish_views' => 'Publish command palette views',
        'theme' => [
            'light' => 'Switch to light theme',
            'dark' => 'Switch to dark theme',
            'system' => 'Match system theme',
        ],
    ],

    'badges' => [
        'record' => 'Record',
    ],

    'empty' => [
        'title' => 'Search your panel',
        'description' => 'Start typing to find a page, a record or an action.',
    ],

    'no_results' => [
        'title' => 'Nothing matches :query',
        'description' => 'Check the spelling, or try a shorter search.',
    ],

    'preview' => [
        'open' => 'Open',
        'group' => 'Group',
        'url' => 'URL',
    ],

    'searching' => 'Searching records',

    'loading' => 'Loading commands',

    'error' => [
        'title' => 'The palette could not load',
        'description' => 'Reload the page to try again.',
    ],

    'modes' => [
        'hint' => 'Type > for actions, # for records, ? for help. Tab to step inside.',
        'help' => [
            'title' => 'Keyboard shortcuts',
            'navigate' => 'Move between results',
            'select' => 'Open the highlighted result',
            'new_tab' => 'Open in a new tab',
            'close' => 'Close the palette',
            'back' => 'Go back',
            'drill' => 'Step inside',
            'pin' => 'Pin or unpin',
            'actions' => 'Show actions only',
            'records' => 'Search records only',
        ],
    ],

    'a11y' => [
        'dialog_label' => 'Command palette',
        'results_label' => 'Results',
        'breadcrumb' => 'Command path',
        'chord' => 'Shortcut :chord',
        'clear' => 'Clear search',
        'no_results' => 'No results',
        'one_result' => '1 result',
        'many_results' => ':count results',
    ],

    'publish_views' => [
        'title' => 'Publish command palette views',
        'navigation_label' => 'Publish views',
        'group' => 'Command palette',
        'action' => 'Publish views',
        'heading' => 'Customise the palette',
        'description' => 'Publishing copies the palette\'s Blade views into your application at resources/views/vendor/command-palette, where you can edit them freely. Your copies take precedence over the package\'s from then on, so future package updates will not reach them.',
        'notification' => [
            'title' => 'Views published',
            'body' => 'Edit them in resources/views/vendor/command-palette.',
        ],
    ],

];
