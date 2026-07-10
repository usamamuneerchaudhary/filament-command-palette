@php
    $emptyTitle = $templateConfig['empty_title'] ?? 'Search commands and pages';
    $emptyDescription = $templateConfig['empty_description'] ?? 'Quickly navigate your panel by running a global search.';
    $noResultsTitle = $templateConfig['no_results_title'] ?? 'No results found';
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
        get filteredCommands() {
            const s = this.search.toLowerCase().trim();
            if (! s) {
                return [];
            }

            let filtered = this.commands.filter(c =>
                c.label.toLowerCase().includes(s) || c.group.toLowerCase().includes(s)
            );

            const grouped = {};
            filtered.forEach(c => {
                if (! grouped[c.group]) {
                    grouped[c.group] = [];
                }
                grouped[c.group].push(c);
            });

            const flat = [];
            let count = 0;
            for (const g of Object.keys(grouped)) {
                flat.push({ type: 'header', group: g });
                const items = grouped[g].slice(0, this.maxResults - count);
                items.forEach(c => flat.push({ type: 'command', ...c }));
                count += items.length;
                if (count >= this.maxResults) {
                    break;
                }
            }

            return flat;
        },
        get visibleCommands() {
            return this.filteredCommands.filter(i => i.type === 'command');
        },
        get visibleCount() {
            return this.visibleCommands.length;
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
        commandIndex(flatIndex) {
            return this.filteredCommands.slice(0, flatIndex).filter(x => x.type === 'command').length;
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
                class="fi-cp-panel fi-cp-panel--sm"
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

                <div x-show="! hasSearch" class="fi-cp-empty">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true" class="fi-cp-empty-icon">
                        <path d="m6.115 5.19.319 1.913A6 6 0 0 0 8.11 10.36L9.75 12l-.387.775c-.217.433-.132.956.21 1.298l1.348 1.348c.21.21.329.497.329.795v1.089c0 .426.24.815.622 1.006l.153.076c.433.217.956.132 1.298-.21l.723-.723a8.7 8.7 0 0 0 2.288-4.042 1.087 1.087 0 0 0-.358-1.099l-1.33-1.108c-.251-.21-.582-.299-.905-.245l-1.17.195a1.125 1.125 0 0 1-.98-.314l-.295-.295a1.125 1.125 0 0 1 0-1.591l.13-.132a1.125 1.125 0 0 1 1.3-.21l.603.302a.809.809 0 0 0 1.086-1.086L14.25 7.5l1.256-.837a4.5 4.5 0 0 0 1.528-1.732l.146-.292M6.115 5.19A9 9 0 1 0 17.18 4.64M6.115 5.19A8.965 8.965 0 0 1 12 3c1.929 0 3.716.607 5.18 1.64" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    <p class="fi-cp-empty-title">{{ $emptyTitle }}</p>
                    <p class="fi-cp-empty-desc">{{ $emptyDescription }}</p>
                </div>

                <div
                    x-show="hasSearch && visibleCount > 0"
                    x-ref="resultsList"
                    class="fi-cp-results command-palette-results"
                >
                    <template x-for="(item, flatIndex) in filteredCommands" :key="item.type === 'header' ? 'h-' + item.group : item.id">
                        <div>
                            <div
                                x-show="item.type === 'header'"
                                class="fi-cp-group-label"
                                x-text="item.group"
                            ></div>
                            <a
                                x-show="item.type === 'command'"
                                :href="item.url"
                                :target="item.openInNewTab ? '_blank' : null"
                                :rel="item.openInNewTab ? 'noopener noreferrer' : null"
                                class="fi-command-palette-link fi-cp-item"
                                x-bind:class="{ 'is-selected': selectedIndex === commandIndex(flatIndex) }"
                                x-on:mouseenter="selectIndex(commandIndex(flatIndex))"
                                x-on:click="close()"
                            >
                                <span class="fi-cp-item-icon" x-html="item.iconHtml"></span>
                                <span class="fi-cp-item-label" x-text="item.label"></span>
                            </a>
                        </div>
                    </template>
                </div>

                <div x-show="hasSearch && visibleCount === 0" class="fi-cp-no-results">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true" class="fi-cp-no-results-icon">
                        <path d="M15.182 16.318A4.486 4.486 0 0 0 12.016 15a4.486 4.486 0 0 0-3.198 1.318M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0ZM9.75 9.75c0 .414-.168.75-.375.75S9 10.164 9 9.75 9.168 9 9.375 9s.375.336.375.75Zm-.375 0h.008v.015h-.008V9.75Zm5.625 0c0 .414-.168.75-.375.75s-.375-.336-.375-.75.168-.75.375-.75.375.336.375.75Zm-.375 0h.008v.015h-.008V9.75Z" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    <p class="fi-cp-no-results-title">{{ $noResultsTitle }}</p>
                    <p class="fi-cp-no-results-desc">{{ $noResultsDescription }}</p>
                </div>
            </div>
        </div>
    </div>
</div>
