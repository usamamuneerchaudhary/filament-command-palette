@php
    $platform = \Filament\Support\Enums\Platform::detect();
    $shortcutHint = $platform === \Filament\Support\Enums\Platform::Mac ? '⌘K' : 'Ctrl+K';
    $showButton = ! filament()->isGlobalSearchEnabled() || config('command-palette.show_topbar_button_when_global_search_enabled', false);
@endphp
@if ($showButton)
<button
    type="button"
    x-data="{}"
    x-on:click="$dispatch('open-command-palette')"
    style="display: flex; align-items: center; width: 100%; max-width: 20rem; gap: 0.75rem; padding: 0.5rem 0.75rem 0.5rem 1rem; border-radius: 0.5rem; border: 1px solid rgb(229 231 235); background: rgb(249 250 251); font-size: 0.875rem; line-height: 1.25rem; color: rgb(107 114 128); text-align: left; transition: border-color 0.15s, background-color 0.15s; min-height: 2.5rem; outline: none; cursor: pointer;"
    class="dark:border-white/10 dark:bg-white/5 dark:text-gray-400 hover:border-gray-300 hover:bg-gray-100 dark:hover:border-white/20 dark:hover:bg-white/10"
>
    <span style="display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; width: 1rem; height: 1rem;">
        <x-filament::icon
            :icon="\Filament\Support\Icons\Heroicon::MagnifyingGlass"
            class="fi-size-4 fi-text-gray-400 dark:fi-text-gray-500"
        />
    </span>
    <span style="flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">Type a command or search...</span>
    <kbd style="flex-shrink: 0; padding: 0.125rem 0.375rem; font-size: 0.75rem; border-radius: 0.25rem; border: 1px solid rgb(229 231 235); background: white; color: rgb(107 114 128);" class="dark:border-white/10 dark:bg-gray-900 dark:text-gray-400">{{ $shortcutHint }}</kbd>
</button>
@endif
