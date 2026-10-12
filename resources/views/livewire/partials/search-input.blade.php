<div class="fi-cp-search">
    <span class="fi-cp-search-icon" aria-hidden="true">
        <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path d="M9 3.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11ZM2 9a7 7 0 1 1 12.452 4.391l3.328 3.329a.75.75 0 1 1-1.06 1.06l-3.329-3.328A7 7 0 0 1 2 9Z" clip-rule="evenodd" fill-rule="evenodd" />
        </svg>
    </span>

    <input
        type="text"
        role="combobox"
        autocomplete="off"
        autocorrect="off"
        autocapitalize="off"
        spellcheck="false"
        class="fi-cp-search-input"
        x-ref="searchInput"
        x-model="query"
        :id="config.id + '-input'"
        :aria-expanded="isOpen.toString()"
        :aria-controls="config.id + '-listbox'"
        :aria-activedescendant="activeDescendant"
        aria-autocomplete="list"
        placeholder="{{ $placeholder }}"
        x-on:keydown.down.prevent="move(1)"
        x-on:keydown.up.prevent="move(-1)"
        x-on:keydown.home.prevent="moveTo(0)"
        x-on:keydown.end.prevent="moveTo(resultCount - 1)"
        x-on:keydown.enter="enter($event)"
        x-on:keydown.escape.stop="close()"
    />

    <template x-if="isSearchingRecords">
        <span class="fi-cp-spinner" role="status" aria-label="{{ __('command-palette::command-palette.searching') }}"></span>
    </template>

    <button
        type="button"
        class="fi-cp-clear"
        x-show="query.length > 0"
        x-cloak
        x-on:click="query = ''; $refs.searchInput?.focus()"
        aria-label="{{ __('command-palette::command-palette.a11y.clear') }}"
    >
        <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" />
        </svg>
    </button>
</div>

{{-- Announced to screen readers without being shown on screen. --}}
<p class="fi-cp-sr-only" aria-live="polite" aria-atomic="true">
    <span x-text="resultCount === 0
        ? @js(__('command-palette::command-palette.a11y.no_results'))
        : (resultCount === 1
            ? @js(__('command-palette::command-palette.a11y.one_result'))
            : @js(__('command-palette::command-palette.a11y.many_results', ['count' => ':count'])).replace(':count', resultCount))"></span>
</p>
