@php
    $keyBindings = config('command-palette.key_bindings', ['mod+k']);
    $mousetrapBindings = collect($keyBindings)->map(fn (string $key): string => str_replace('+', '-', $key))->implode('.');
@endphp

<style>
.command-palette-results::-webkit-scrollbar { width: 6px; }
.command-palette-results::-webkit-scrollbar-track { background: transparent; }
.command-palette-results::-webkit-scrollbar-thumb {
    background: rgb(203 213 225);
    border-radius: 3px;
}
.command-palette-results::-webkit-scrollbar-thumb:hover {
    background: rgb(148 163 184);
}
.dark .command-palette-results::-webkit-scrollbar-thumb {
    background: rgb(71 85 105);
}
.dark .command-palette-results::-webkit-scrollbar-thumb:hover {
    background: rgb(100 116 139);
}
.command-palette-results { scrollbar-width: thin; scrollbar-color: rgb(203 213 225) transparent; }
.dark .command-palette-results { scrollbar-color: rgb(71 85 105) transparent; }
</style>
<div
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
        get filteredCommands() {
            const s = this.search.toLowerCase().trim();
            let filtered = this.commands;
            if (s) {
                filtered = filtered.filter(c =>
                    c.label.toLowerCase().includes(s) || c.group.toLowerCase().includes(s)
                );
            }
            const grouped = {};
            filtered.forEach(c => {
                if (!grouped[c.group]) grouped[c.group] = [];
                grouped[c.group].push(c);
            });
            const flat = [];
            let count = 0;
            for (const g of Object.keys(grouped)) {
                flat.push({ type: 'header', group: g });
                const items = grouped[g].slice(0, this.maxResults - count);
                items.forEach(c => flat.push({ type: 'command', ...c }));
                count += items.length;
                if (count >= this.maxResults) break;
            }
            return flat;
        },
        get visibleCount() {
            return this.filteredCommands.filter(i => i.type === 'command').length;
        },
    }"
    x-init="$watch('search', () => selectedIndex = 0)"
    x-mousetrap.global.{{ $mousetrapBindings }}="isOpen = true; $nextTick(() => { $refs.searchInput?.focus(); selectedIndex = 0 })"
    x-on:keydown.escape.window="isOpen = false"
    x-on:open-command-palette.window="isOpen = true; $nextTick(() => { $refs.searchInput?.focus(); selectedIndex = 0 })"
>
    <div
        x-ref="overlayContainer"
        x-show="isOpen"
        x-cloak
        x-transition:enter="fi-transition-enter"
        x-transition:enter-start="fi-transition-enter-start"
        x-transition:enter-end="fi-transition-enter-end"
        x-transition:leave="fi-transition-leave"
        x-transition:leave-start="fi-transition-leave-start"
        x-transition:leave-end="fi-transition-leave-end"
        x-bind:class="{ 'fi-modal-open': isOpen }"
        class="fi-modal fi-absolute-positioning-context"
        style="position: fixed; inset: 0; z-index: 9999;"
        x-on:click.self="isOpen = false"
        aria-modal="true"
        role="dialog"
    >
        <div
            aria-hidden="true"
            x-show="isOpen"
            x-transition.duration.300ms.opacity
            class="fi-modal-close-overlay"
        ></div>

        <div class="fi-modal-window-ctn fi-clickable">
            <div class="fi-modal-window fi-modal-window-has-content fi-width-lg fi-align-center" style="max-height: min(28rem, 85vh); overflow: hidden; display: flex; flex-direction: column;">
                <div class="fi-modal-content !p-0" style="display: flex; flex-direction: column; min-height: 0; overflow: hidden;">
                    <div class="fi-border-b fi-border-gray-200 dark:fi-border-white/10" style="padding: 0.1875rem 0.5rem; flex-shrink: 0;">
                        <x-filament::input.wrapper
                            :prefix-icon="\Filament\Support\Icons\Heroicon::MagnifyingGlass"
                            inline-prefix
                            style="align-items: center;"
                        >
                            <input
                                type="search"
                                x-model="search"
                                x-ref="searchInput"
                                placeholder="Type a command or search..."
                                autocomplete="off"
                                class="fi-input fi-input-has-inline-prefix fi-block fi-w-full"
                                x-on:keydown.down.prevent="selectedIndex = Math.min(selectedIndex + 1, visibleCount - 1); $refs.resultsList?.querySelectorAll('a.fi-command-palette-link')[selectedIndex]?.scrollIntoView({ block: 'nearest' })"
                                x-on:keydown.up.prevent="selectedIndex = Math.max(selectedIndex - 1, 0); $refs.resultsList?.querySelectorAll('a.fi-command-palette-link')[selectedIndex]?.scrollIntoView({ block: 'nearest' })"
                                x-on:keydown.enter.prevent="$refs.resultsList?.querySelectorAll('a.fi-command-palette-link')[selectedIndex]?.click()"
                            />
                        </x-filament::input.wrapper>
                    </div>

                    <div
                        x-ref="resultsList"
                        class="command-palette-results"
                        style="flex: 1; min-height: 0; overflow-y: auto; padding: 0; max-height: 12rem;"
                    >
                        <template x-if="visibleCount === 0">
                            <p style="padding: 0.75rem 0.625rem;" class="fi-text-center fi-text-sm fi-text-gray-500 dark:fi-text-gray-400">
                                No commands found. Try a different search.
                            </p>
                        </template>
                        <template x-if="visibleCount > 0">
                            <ul role="listbox" style="padding: 0.125rem 0;" class="fi-border-t fi-border-gray-200 dark:fi-border-white/10">
                                <template x-for="(item, flatIndex) in filteredCommands" :key="item.type === 'header' ? 'h-' + item.group : item.id">
                                    <li
                                        :role="item.type === 'header' ? 'presentation' : 'option'"
                                        :style="item.type === 'header' ? 'padding: 0.5rem 0.5rem 0.125rem; letter-spacing: 0.07em; font-size: 0.65rem; font-weight: 600; text-transform: uppercase; color: #6b7280;' : ''"
                                        x-show="true"
                                    >
                                        <span x-show="item.type === 'header'" x-text="item.group"></span>
                                        <a
                                            x-show="item.type === 'command'"
                                            :href="item.url"
                                            :target="item.openInNewTab ? '_blank' : null"
                                            :rel="item.openInNewTab ? 'noopener noreferrer' : null"
                                            class="fi-command-palette-link flex items-center gap-2 px-2 py-1.5 rounded-md text-sm font-medium text-gray-900 dark:text-white transition-colors duration-100 outline-none focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-1 hover:bg-gray-100 dark:hover:bg-white/5 focus:bg-gray-100 dark:focus:bg-white/5 cursor-pointer"
                                            x-bind:class="{ 'fi-bg-gray-50 dark:fi-bg-white/5': selectedIndex === filteredCommands.slice(0, flatIndex).filter(x => x.type === 'command').length }"
                                            x-on:mouseenter="selectedIndex = filteredCommands.slice(0, flatIndex).filter(x => x.type === 'command').length"
                                            x-on:click="isOpen = false"
                                        >
                                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                                <span style="display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; width: 1rem; height: 1rem; opacity: 0.5;" x-html="item.iconHtml"></span>
                                                <span style="font-size: 0.875rem; font-weight: 500; color: inherit;" class="fi-truncate fi-text-gray-950 dark:fi-text-white" x-text="item.label"></span>
                                            </div>
                                        </a>
                                    </li>
                                </template>
                            </ul>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
