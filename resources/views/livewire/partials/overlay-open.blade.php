@php
    $panelClass = $panelClass ?? 'fi-cp-panel--sm';
    $variant = $variant ?? 'standard';
@endphp

<div
    class="fi-cp-root fi-cp-root--{{ $variant }}"
    x-data="filamentCommandPalette(@js($paletteConfig))"
    x-mousetrap.global.{{ $mousetrapBindings }}="open()"
    x-on:keydown.escape.window="close()"
    x-on:open-command-palette.window="open()"
>
    <div
        x-show="isOpen"
        x-cloak
        class="fi-cp-overlay-wrap"
        role="dialog"
        aria-modal="true"
        aria-label="{{ __('command-palette::command-palette.a11y.dialog_label') }}"
        x-on:keydown.tab="trapFocus($event)"
    >
        <div
            x-show="isOpen"
            x-transition.opacity.duration.300ms
            class="fi-cp-backdrop"
            x-on:click="close()"
        ></div>

        <div class="fi-cp-scroll" x-on:click.self="close()">
            <div
                x-show="isOpen"
                x-ref="dialog"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="fi-cp-panel {{ $panelClass }}"
            >
