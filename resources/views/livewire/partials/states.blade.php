@php
    $emptyTitle = $templateConfig['empty_title'] ?? null;
    $emptyDescription = $templateConfig['empty_description'] ?? null;
@endphp

<template x-if="isLoading">
    <div class="fi-cp-state" role="status">
        <span class="fi-cp-spinner fi-cp-spinner--lg" aria-hidden="true"></span>
        <p class="fi-cp-state-title">{{ __('command-palette::command-palette.loading') }}</p>
    </div>
</template>

<template x-if="loadFailed">
    <div class="fi-cp-state" role="alert">
        <span class="fi-cp-state-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                <path d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </span>
        <p class="fi-cp-state-title">{{ __('command-palette::command-palette.error.title') }}</p>
        <p class="fi-cp-state-description">{{ __('command-palette::command-palette.error.description') }}</p>
    </div>
</template>

<template x-if="showHelp">
    <dl class="fi-cp-help">
        @foreach ([
            'navigate' => '↑ ↓',
            'select' => '↵',
            'new_tab' => '⌘ ↵',
            'close' => 'esc',
            'actions' => '>',
            'records' => '#',
        ] as $key => $keys)
            <div class="fi-cp-help-row">
                <dt class="fi-cp-help-label">{{ __("command-palette::command-palette.modes.help.{$key}") }}</dt>
                <dd class="fi-cp-help-keys"><kbd class="fi-cp-kbd">{{ $keys }}</kbd></dd>
            </div>
        @endforeach
    </dl>
</template>

<template x-if="isEmpty && !loadFailed && !isSearchingRecords">
    <div class="fi-cp-state">
        <span class="fi-cp-state-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                <path d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </span>

        <template x-if="term.length === 0">
            <div>
                <p class="fi-cp-state-title">{{ $emptyTitle ?? __('command-palette::command-palette.empty.title') }}</p>
                <p class="fi-cp-state-description">{{ $emptyDescription ?? __('command-palette::command-palette.empty.description') }}</p>
            </div>
        </template>

        <template x-if="term.length > 0">
            <div>
                <p
                    class="fi-cp-state-title"
                    x-text="@js(__('command-palette::command-palette.no_results.title', ['query' => ':query'])).replace(':query', '“' + term + '”')"
                ></p>
                <p class="fi-cp-state-description">{{ __('command-palette::command-palette.no_results.description') }}</p>
            </div>
        </template>
    </div>
</template>
