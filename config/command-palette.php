<?php

use Usamamuneerchaudhary\CommandPalette\Support\CommandItem;

return [
    /*
    |--------------------------------------------------------------------------
    | Key Bindings
    |--------------------------------------------------------------------------
    | Keyboard shortcut to open the command palette.
    | Use 'mod' for CMD on Mac and Ctrl on Windows/Linux.
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
