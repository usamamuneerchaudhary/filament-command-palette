@include('command-palette::livewire.partials.overlay-open', [
    'panelClass' => 'fi-cp-panel--lg',
    'variant' => 'preview',
])
    @include('command-palette::livewire.partials.search-input')

    <div class="fi-cp-body">
        <div class="fi-cp-list-pane">
            @include('command-palette::livewire.partials.states')
            @include('command-palette::livewire.partials.results-list', [
                'itemClass' => 'fi-cp-preview-item',
                'resultsClass' => 'command-palette-results',
                'withChevron' => true,
            ])
        </div>

        <div
            x-show="selected && !isLoading && !loadFailed && !showHelp"
            x-cloak
            class="fi-cp-preview-pane"
        >
            <div class="fi-cp-preview-header">
                <div class="fi-cp-preview-avatar" x-html="selected?.iconHtml"></div>
                <h2 class="fi-cp-preview-title" x-html="selected?.htmlLabel || selected?.labelHtml || ''"></h2>
                <p class="fi-cp-preview-subtitle" x-text="selected?.description || selected?.group"></p>
            </div>

            <div class="fi-cp-preview-body">
                <dl class="fi-cp-preview-dl">
                    <template x-for="[key, value] in previewEntries(selected)" :key="key">
                        <div class="contents">
                            <dt class="fi-cp-preview-dt" x-text="key"></dt>
                            <dd class="fi-cp-preview-dd" x-text="value"></dd>
                        </div>
                    </template>
                </dl>

                <div class="fi-cp-preview-actions" x-show="selected?.resultActions?.length" x-cloak>
                    <template x-for="action in (selected?.resultActions ?? [])" :key="action.url + action.label">
                        <a
                            :href="action.url"
                            :target="action.openInNewTab ? '_blank' : null"
                            :rel="action.openInNewTab ? 'noopener noreferrer' : null"
                            class="fi-cp-preview-action"
                            x-text="action.label"
                        ></a>
                    </template>
                </div>

                <a
                    x-show="selected && !selected.hasChildren"
                    :href="selected?.url || '#'"
                    :target="selected?.openInNewTab ? '_blank' : null"
                    :rel="selected?.openInNewTab ? 'noopener noreferrer' : null"
                    class="fi-cp-open-btn"
                    x-on:click="handleClick(selected, $event)"
                >
                    {{ __('command-palette::command-palette.preview.open') }}
                </a>
            </div>
        </div>
    </div>

    @include('command-palette::livewire.partials.footer')
@include('command-palette::livewire.partials.overlay-close')
