@php
    $itemClass = $itemClass ?? 'fi-cp-item';
    $resultsClass = $resultsClass ?? 'fi-cp-results command-palette-results';
    $withChevron = $withChevron ?? false;
@endphp

<ul
    :id="config.id + '-listbox'"
    role="listbox"
    aria-label="{{ __('command-palette::command-palette.a11y.results_label') }}"
    class="{{ $resultsClass }}"
    x-ref="results"
    x-show="!isLoading && !loadFailed && !showHelp && resultCount > 0"
    x-cloak
>
    <template x-for="item in flat" :key="item.id">
        <li role="presentation">
            <div
                x-show="item.type === 'header'"
                class="fi-cp-group-label"
                x-text="item.group"
            ></div>

            <a
                x-show="item.type === 'command'"
                :href="item.url || '#'"
                :id="optionId(item.position)"
                :data-index="item.position"
                role="option"
                :aria-selected="(selectedIndex === item.position).toString()"
                :target="item.openInNewTab ? '_blank' : null"
                :rel="item.openInNewTab ? 'noopener noreferrer' : null"
                class="fi-command-palette-link {{ $itemClass }}"
                x-bind:class="{ 'is-selected': selectedIndex === item.position }"
                x-on:mouseenter="selectedIndex = item.position"
                x-on:click="handleClick(item, $event)"
            >
                <span class="fi-cp-item-icon" x-html="item.iconHtml"></span>
                <span class="fi-cp-item-label" x-html="item.labelHtml"></span>
                <span class="fi-cp-badge" x-show="item.badge" x-cloak x-text="item.badge"></span>
                @if ($withChevron)
                    <svg
                        viewBox="0 0 20 20"
                        fill="currentColor"
                        aria-hidden="true"
                        class="fi-cp-chevron"
                        x-show="selectedIndex === item.position"
                    >
                        <path d="M8.22 5.22a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L11.94 10 8.22 6.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" fill-rule="evenodd" />
                    </svg>
                @endif
            </a>
        </li>
    </template>
</ul>
