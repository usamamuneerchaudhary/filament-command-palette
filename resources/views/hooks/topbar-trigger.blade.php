@php
    $shortcutHint = __('command-palette::command-palette.trigger.shortcut_other');
    if (class_exists(\Filament\Support\Enums\Platform::class)) {
        try {
            $platform = \Filament\Support\Enums\Platform::detect();
            $shortcutHint = $platform === \Filament\Support\Enums\Platform::Mac
                ? __('command-palette::command-palette.trigger.shortcut_mac')
                : __('command-palette::command-palette.trigger.shortcut_other');
        } catch (\Throwable $e) {
            // Platform::detect() may not exist in Filament 4
        }
    }
    $settings = \Usamamuneerchaudhary\CommandPalette\Support\PaletteSettings::for(filament()->getCurrentPanel());
    $showButton = $settings->bool('show_topbar_button', true)
        && (! filament()->isGlobalSearchEnabled() || $settings->bool('show_topbar_button_when_global_search_enabled', false));
@endphp
@if ($showButton)
<button
    type="button"
    class="fi-cp-trigger"
    x-data="{}"
    x-on:click="$dispatch('open-command-palette')"
>
    <span class="fi-cp-trigger-icon" aria-hidden="true">
        <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path d="M9 3.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11ZM2 9a7 7 0 1 1 12.452 4.391l3.328 3.329a.75.75 0 1 1-1.06 1.06l-3.329-3.328A7 7 0 0 1 2 9Z" clip-rule="evenodd" fill-rule="evenodd" />
        </svg>
    </span>
    <span class="fi-cp-trigger-label">{{ __('command-palette::command-palette.trigger.label') }}</span>
    <kbd class="fi-cp-trigger-kbd">{{ $shortcutHint }}</kbd>
</button>
@endif
