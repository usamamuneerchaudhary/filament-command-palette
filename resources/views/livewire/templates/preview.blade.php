@php
    $emptyHeading = $templateConfig['empty_heading'] ?? 'Recent commands';
    $noResultsTitle = $templateConfig['no_results_title'] ?? 'No commands found';
    $noResultsDescription = $templateConfig['no_results_description'] ?? 'We couldn\'t find anything with that term. Please try again.';
@endphp

<div
    class="fi-cp-root"
    data-commands="{{ $commandsBase64 }}"
    x-data="{
        commands: [],
        search: '',
        maxResults: {{ $maxResults }},
        selectedIndex: 0,
        isOpen: false,
        init() {
            try {
                const b64 = this.$el.dataset.commands;
                this.commands = b64 ? JSON.parse(atob(b64)) : [];
            } catch (e) {
                this.commands = [];
            }
        },
        get hasSearch() {
            return this.search.trim().length > 0;
        },
        get listCommands() {
            const s = this.search.toLowerCase().trim();
            if (! s) {
                return this.commands.slice(0, this.maxResults);
            }

            return this.commands
                .filter(c => c.label.toLowerCase().includes(s) || c.group.toLowerCase().includes(s))
                .slice(0, this.maxResults);
        },
        get visibleCount() {
            return this.listCommands.length;
        },
        get selectedCommand() {
            return this.listCommands[this.selectedIndex] ?? null;
        },
        previewEntries(command) {
            if (! command) {
                return [];
            }
            if (command.preview && typeof command.preview === 'object') {
                return Object.entries(command.preview);
            }
            return [
                ['Group', command.group],
                ['URL', command.url],
            ];
        },
        open() {
            this.isOpen = true;
            this.search = '';
            this.selectedIndex = 0;
            this.$nextTick(() => this.$refs.searchInput?.focus());
        },
        close() {
            this.isOpen = false;
        },
        selectIndex(index) {
            this.selectedIndex = index;
        },
        moveSelection(delta) {
            if (this.visibleCount === 0) {
                return;
            }
            this.selectedIndex = Math.max(0, Math.min(this.selectedIndex + delta, this.visibleCount - 1));
            this.$nextTick(() => {
                this.$refs.resultsList?.querySelectorAll('a.fi-command-palette-link')[this.selectedIndex]?.scrollIntoView({ block: 'nearest' });
            });
        },
        activateSelected() {
            this.$refs.resultsList?.querySelectorAll('a.fi-command-palette-link')[this.selectedIndex]?.click();
        },
    }"
    x-init="$watch('search', () => selectedIndex = 0)"
    x-mousetrap.global.{{ $mousetrapBindings }}="open()"
    x-on:keydown.escape.window="close()"
    x-on:open-command-palette.window="open()"
>
    <div
        x-show="isOpen"
        x-cloak
        class="fi-cp-overlay-wrap"
        aria-modal="true"
        role="dialog"
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
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="fi-cp-panel fi-cp-panel--lg"
            >
                <div class="fi-cp-search">
                    <input
                        type="search"
                        x-model="search"
                        x-ref="searchInput"
                        placeholder="Search..."
                        autocomplete="off"
                        class="fi-cp-search-input"
                        x-on:keydown.down.prevent="moveSelection(1)"
                        x-on:keydown.up.prevent="moveSelection(-1)"
                        x-on:keydown.enter.prevent="activateSelected()"
                    />
                    <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="fi-cp-search-icon">
                        <path d="M9 3.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11ZM2 9a7 7 0 1 1 12.452 4.391l3.328 3.329a.75.75 0 1 1-1.06 1.06l-3.329-3.328A7 7 0 0 1 2 9Z" clip-rule="evenodd" fill-rule="evenodd" />
                    </svg>
                </div>

                <div class="fi-cp-body">
                    <div class="fi-cp-list-pane">
                        <div x-ref="resultsList" class="command-palette-results">
                            <template x-if="! hasSearch && visibleCount > 0">
                                <h2 class="fi-cp-list-heading">{{ $emptyHeading }}</h2>
                            </template>

                            <template x-if="visibleCount > 0">
                                <div>
                                    <template x-for="(item, index) in listCommands" :key="item.id">
                                        <a
                                            :href="item.url"
                                            :target="item.openInNewTab ? '_blank' : null"
                                            :rel="item.openInNewTab ? 'noopener noreferrer' : null"
                                            class="fi-command-palette-link fi-cp-preview-item"
                                            x-bind:class="{ 'is-selected': selectedIndex === index }"
                                            x-on:mouseenter="selectIndex(index)"
                                            x-on:click="close()"
                                        >
                                            <span class="fi-cp-avatar" x-html="item.iconHtml"></span>
                                            <span class="fi-cp-preview-item-label" x-text="item.label"></span>
                                            <svg
                                                viewBox="0 0 20 20"
                                                fill="currentColor"
                                                aria-hidden="true"
                                                class="fi-cp-chevron"
                                                x-show="selectedIndex === index"
                                            >
                                                <path d="M8.22 5.22a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L11.94 10 8.22 6.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" fill-rule="evenodd" />
                                            </svg>
                                        </a>
                                    </template>
                                </div>
                            </template>

                            <template x-if="hasSearch && visibleCount === 0">
                                <div class="fi-cp-preview-empty">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true" class="fi-cp-no-results-icon">
                                        <path d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                    <p class="fi-cp-no-results-title">{{ $noResultsTitle }}</p>
                                    <p class="fi-cp-no-results-desc">{{ $noResultsDescription }}</p>
                                </div>
                            </template>
                        </div>
                    </div>

                    <div x-show="selectedCommand" class="fi-cp-preview-pane">
                        <div class="fi-cp-preview-header">
                            <div class="fi-cp-preview-avatar" x-html="selectedCommand?.iconHtml"></div>
                            <h2 class="fi-cp-preview-title" x-text="selectedCommand?.label"></h2>
                            <p class="fi-cp-preview-subtitle" x-text="selectedCommand?.description || selectedCommand?.group"></p>
                        </div>
                        <div class="fi-cp-preview-body">
                            <dl class="fi-cp-preview-dl">
                                <template x-for="[key, value] in previewEntries(selectedCommand)" :key="key">
                                    <div class="contents">
                                        <dt class="fi-cp-preview-dt" x-text="key"></dt>
                                        <dd class="fi-cp-preview-dd" x-text="value"></dd>
                                    </div>
                                </template>
                            </dl>
                            <a
                                x-show="selectedCommand"
                                :href="selectedCommand?.url"
                                :target="selectedCommand?.openInNewTab ? '_blank' : null"
                                :rel="selectedCommand?.openInNewTab ? 'noopener noreferrer' : null"
                                class="fi-cp-open-btn"
                                x-on:click="close()"
                            >
                                Open
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
