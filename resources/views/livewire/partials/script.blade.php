@once
    <script>
        document.addEventListener('alpine:init', () => {
            if (window.__filamentCommandPaletteRegistered) return
            window.__filamentCommandPaletteRegistered = true

            const WORD_BOUNDARY = /[\s\-_/:.,()\[\]]/

            /**
             * Subsequence match with bonuses for prefixes, word starts and runs.
             * Returns null when the needle is not present at all, otherwise a
             * score plus the matched positions so the label can be highlighted.
             */
            function fuzzy(needle, haystack) {
                if (!needle) return { score: 0, positions: [] }
                if (!haystack) return null

                const hay = haystack.toLowerCase()
                const exact = hay.indexOf(needle)

                if (exact !== -1) {
                    const positions = []
                    for (let i = 0; i < needle.length; i++) positions.push(exact + i)

                    let score = 120 - exact
                    if (exact === 0) score += 60
                    else if (WORD_BOUNDARY.test(hay[exact - 1])) score += 35

                    return { score, positions }
                }

                const positions = []
                let score = 0
                let hayIndex = 0
                let run = 0

                for (let i = 0; i < needle.length; i++) {
                    const found = hay.indexOf(needle[i], hayIndex)
                    if (found === -1) return null

                    let points = 8
                    if (found === 0) points += 25
                    else if (WORD_BOUNDARY.test(hay[found - 1])) points += 18

                    if (found === hayIndex && i > 0) {
                        run += 1
                        points += run * 6
                    } else {
                        run = 0
                        points -= Math.min(found - hayIndex, 8)
                    }

                    score += points
                    positions.push(found)
                    hayIndex = found + 1
                }

                return { score: Math.max(score, 1), positions }
            }

            function escapeHtml(value) {
                return String(value ?? '').replace(/[&<>"']/g, (c) => ({
                    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
                }[c]))
            }

            /**
             * Escape first, then wrap matched characters. Labels come from
             * application code and translations, so they are never trusted.
             */
            function highlight(label, positions) {
                const safe = String(label ?? '')
                if (!positions || !positions.length) return escapeHtml(safe)

                const marked = new Set(positions)
                let html = ''
                let open = false

                for (let i = 0; i < safe.length; i++) {
                    const hit = marked.has(i)
                    if (hit && !open) { html += '<mark class="fi-cp-mark">'; open = true }
                    if (!hit && open) { html += '</mark>'; open = false }
                    html += escapeHtml(safe[i])
                }

                return open ? html + '</mark>' : html
            }

            Alpine.data('filamentCommandPalette', (config) => ({
                config,
                isOpen: false,
                isLoading: false,
                isSearchingRecords: false,
                hasLoaded: false,
                loadFailed: false,
                query: '',
                mode: null,
                commands: [],
                icons: {},
                records: [],
                recents: [],
                pins: [],
                searchScope: null,
                selectedIndex: 0,
                lastFocused: null,
                searchToken: 0,
                searchTimer: null,
                stack: [],
                breadcrumbs: [],
                submenuCommands: null,
                pageClass: null,
                recordKey: null,
                hostComponentId: null,
                hasTableSelection: false,
                pendingChord: null,
                chordTimer: null,

                init() {
                    this.recents = this.readRecents()
                    this.pins = this.readPins()
                    this.bindChords()
                    this.bindPinShortcut()
                    this.$watch('query', () => {
                        this.selectedIndex = 0
                        this.parseMode()
                        this.queueRecordSearch()
                    })
                },

                async open() {
                    if (this.isOpen) return

                    this.lastFocused = document.activeElement
                    this.isOpen = true
                    this.lockScroll(true)
                    this.resetStack()

                    await this.load()

                    this.$nextTick(() => {
                        this.selectedIndex = 0
                        this.$refs.searchInput?.focus()
                        this.$refs.searchInput?.select()
                    })
                },

                requestClose() {
                    if (!this.isOpen) return

                    if (this.stack.length) {
                        this.popStack()
                        return
                    }

                    this.close()
                },

                close() {
                    if (!this.isOpen) return

                    this.isOpen = false
                    this.query = ''
                    this.mode = null
                    this.records = []
                    this.selectedIndex = 0
                    this.resetStack()
                    this.lockScroll(false)

                    // Put focus back where the person left it.
                    this.$nextTick(() => {
                        if (this.lastFocused && document.contains(this.lastFocused)) {
                            this.lastFocused.focus()
                        }
                    })
                },

                toggle() {
                    this.isOpen ? this.close() : this.open()
                },

                lockScroll(locked) {
                    document.documentElement.classList.toggle('fi-cp-locked', locked)
                },

                async load() {
                    if (this.isLoading) return

                    this.isLoading = true
                    this.loadFailed = false
                    this.captureContext()

                    try {
                        const context = this.contextPayload()
                        const response = await this.$wire.loadCommands(context.pageClass, context.recordKey)
                        this.commands = response?.commands ?? []
                        this.icons = { ...this.icons, ...(response?.icons ?? {}) }
                        this.hasLoaded = true
                    } catch (error) {
                        this.loadFailed = true
                        console.error('[command-palette] Could not load commands.', error)
                    } finally {
                        this.isLoading = false
                    }
                },

                captureContext() {
                    const context = this.discoverContext()
                    this.pageClass = context.pageClass
                    this.recordKey = context.recordKey
                    this.hostComponentId = context.hostId
                    this.hasTableSelection = this.discoverTableSelection()
                },

                contextPayload() {
                    return {
                        pageClass: this.pageClass,
                        recordKey: this.recordKey,
                        parentId: this.stack.length ? (this.stack[this.stack.length - 1].parentId ?? null) : null,
                    }
                },

                discoverContext() {
                    const found = { pageClass: null, recordKey: null, hostId: null }

                    for (const el of document.querySelectorAll('[wire\\:snapshot]')) {
                        if (el.closest('.fi-cp-root')) continue

                        try {
                            const snapshot = JSON.parse(el.getAttribute('wire:snapshot'))
                            const name = snapshot?.memo?.name
                            if (!name || String(name).includes('command-palette')) continue

                            found.pageClass = name
                            found.hostId = el.getAttribute('wire:id')
                            found.recordKey = this.extractRecordKey(snapshot?.data?.record)
                            break
                        } catch (error) {
                            // Snapshot parse failures are ignored; the next node may be the page.
                        }
                    }

                    return found
                },

                extractRecordKey(record) {
                    if (record === null || record === undefined || record === '') return null
                    if (typeof record === 'object') {
                        return record.key ?? record.id ?? record.s ?? null
                    }
                    return record
                },

                discoverTableSelection() {
                    for (const el of document.querySelectorAll('[wire\\:snapshot]')) {
                        if (el.closest('.fi-cp-root')) continue

                        try {
                            const data = JSON.parse(el.getAttribute('wire:snapshot'))?.data ?? {}
                            const selected = data.selectedTableRecords ?? data.selectedRecords
                            if (Array.isArray(selected) && selected.length) return true
                            if (selected && typeof selected === 'object' && !Array.isArray(selected) && Object.keys(selected).length) {
                                return true
                            }
                        } catch (error) {
                            // Keep scanning.
                        }
                    }

                    return false
                },

                resetStack() {
                    this.stack = []
                    this.breadcrumbs = []
                    this.submenuCommands = null
                    this.searchScope = null
                },

                async enterSubmenu(command) {
                    this.stack.push({
                        query: this.query,
                        mode: this.mode,
                        records: this.records,
                        commands: this.submenuCommands,
                        selectedIndex: this.selectedIndex,
                        breadcrumb: command.label,
                        parentId: command.id,
                        searchScope: this.searchScope,
                    })
                    this.breadcrumbs = this.stack.map((frame) => frame.breadcrumb)
                    this.query = ''
                    this.mode = null
                    this.records = []
                    this.selectedIndex = 0
                    this.searchScope = command.resourceScope ?? null
                    this.isLoading = true

                    try {
                        const context = this.contextPayload()
                        const response = await this.$wire.loadChildren(command.id, context.pageClass, context.recordKey)
                        this.submenuCommands = response?.commands ?? []
                        this.icons = { ...this.icons, ...(response?.icons ?? {}) }
                    } catch (error) {
                        this.stack.pop()
                        this.breadcrumbs = this.stack.map((frame) => frame.breadcrumb)
                        console.error('[command-palette] Could not load submenu.', error)
                    } finally {
                        this.isLoading = false
                        this.queueRecordSearch()
                        this.$nextTick(() => this.$refs.searchInput?.focus())
                    }
                },

                popStack() {
                    const previous = this.stack.pop()
                    if (!previous) return

                    this.breadcrumbs = this.stack.map((frame) => frame.breadcrumb)
                    this.query = previous.query
                    this.mode = previous.mode
                    this.records = previous.records
                    this.submenuCommands = previous.commands
                    this.selectedIndex = previous.selectedIndex ?? 0
                    this.searchScope = previous.searchScope ?? null
                    this.queueRecordSearch()
                    this.$nextTick(() => this.$refs.searchInput?.focus())
                },

                popTo(index) {
                    while (this.stack.length > index) {
                        this.popStack()
                    }
                },

                parseMode() {
                    if (!this.config.modesEnabled) { this.mode = null; return }

                    const first = this.query.charAt(0)
                    this.mode = ({ '>': 'actions', '#': 'records', '?': 'help' })[first] ?? null
                },

                get term() {
                    return (this.mode ? this.query.slice(1) : this.query).trim().toLowerCase()
                },

                get showHelp() {
                    return this.mode === 'help'
                },

                queueRecordSearch() {
                    if (!this.config.recordSearch || this.mode === 'actions' || this.mode === 'help') {
                        this.records = []
                        return
                    }

                    if (this.stack.length && !this.searchScope) {
                        this.records = []
                        return
                    }

                    clearTimeout(this.searchTimer)

                    const term = this.term

                    if (term.length < this.config.minCharacters) {
                        this.records = []
                        this.isSearchingRecords = false
                        return
                    }

                    this.isSearchingRecords = true
                    const token = ++this.searchToken

                    this.searchTimer = setTimeout(async () => {
                        try {
                            const results = await this.$wire.searchRecords(term, this.searchScope)
                            // A slower earlier request must not overwrite a newer one.
                            if (token !== this.searchToken) return
                            this.records = results ?? []
                        } catch (error) {
                            if (token === this.searchToken) this.records = []
                        } finally {
                            if (token === this.searchToken) this.isSearchingRecords = false
                        }
                    }, this.config.debounce)
                },

                get pool() {
                    const base = this.stack.length ? (this.submenuCommands ?? []) : this.commands

                    if (this.mode === 'records') {
                        if (this.stack.length && !this.searchScope) return []
                        return this.records
                    }
                    if (this.mode === 'actions') {
                        return base.filter((c) => c.type !== 'link' || c.group === this.config.actionsGroup)
                    }
                    if (this.searchScope) return base.concat(this.records)
                    if (this.stack.length) return base

                    return base.concat(this.records)
                },

                visibleCommands(source) {
                    return source.filter((command) => !command.requiresTableSelection || this.hasTableSelection)
                },

                get results() {
                    const term = this.term

                    if (!term) {
                        return this.defaultResults()
                    }

                    const scored = []

                    for (const command of this.visibleCommands(this.pool)) {
                        const match = this.scoreCommand(command, term)
                        if (match) scored.push(match)
                    }

                    scored.sort((a, b) => b.score - a.score)

                    return scored.slice(0, this.config.maxResults)
                },

                defaultResults() {
                    if (this.mode === 'records') return []

                    const source = this.visibleCommands(this.pool)

                    const contextItems = source
                        .filter((command) => command.context)
                        .sort((a, b) => (b.priority ?? 0) - (a.priority ?? 0))
                        .map((command) => this.decorate(command, []))

                    const contextIds = new Set(contextItems.map((command) => command.id))

                    const recentIds = this.recents.map((r) => r.id)
                    const byId = new Map(source.map((c) => [c.id, c]))

                    const pinned = this.config.pinsEnabled && !this.stack.length
                        ? this.resolvePins(source, contextIds)
                        : []
                    const pinnedIds = new Set(pinned.map((command) => command.id))

                    const recent = this.config.recentsEnabled && !this.stack.length
                        ? recentIds
                            .map((id) => byId.get(id))
                            .filter((command) => command && !contextIds.has(command.id) && !pinnedIds.has(command.id))
                            .slice(0, this.config.maxRecents)
                            .map((c) => this.decorate(c, [], { group: this.config.recentsGroup }))
                        : []

                    const recentSet = new Set(recent.map((c) => c.id))
                    const taken = contextItems.length + pinned.length + recent.length

                    const rest = source
                        .filter((c) => !contextIds.has(c.id) && !pinnedIds.has(c.id) && !recentSet.has(c.id))
                        .sort((a, b) => (b.priority ?? 0) - (a.priority ?? 0))
                        .slice(0, Math.max(0, this.config.maxResults - taken))
                        .map((c) => this.decorate(c, []))

                    return contextItems.concat(pinned).concat(recent).concat(rest)
                },

                scoreCommand(command, term) {
                    const label = fuzzy(term, command.label)
                    const group = fuzzy(term, command.group)
                    const description = command.description ? fuzzy(term, command.description) : null

                    let keyword = null
                    for (const word of command.keywords ?? []) {
                        const match = fuzzy(term, word)
                        if (match && (!keyword || match.score > keyword.score)) keyword = match
                    }

                    const best = Math.max(
                        label ? label.score : -1,
                        keyword ? keyword.score * 0.8 : -1,
                        group ? group.score * 0.5 : -1,
                        description ? description.score * 0.4 : -1,
                    )

                    if (best < 0) return null

                    return this.decorate(command, label ? label.positions : [], {
                        score: best + (command.priority ?? 0) + this.frecency(command.id),
                    })
                },

                decorate(command, positions, extra = {}) {
                    return {
                        ...command,
                        score: 0,
                        ...extra,
                        labelHtml: highlight(command.label, positions),
                        iconHtml: this.iconFor(command),
                    }
                },

                iconFor(command) {
                    return this.icons[command.icon] ?? this.icons.__fallback ?? ''
                },

                get grouped() {
                    const groups = []
                    const index = new Map()

                    this.results.forEach((command, position) => {
                        const name = command.group || this.config.actionsGroup

                        if (!index.has(name)) {
                            index.set(name, { group: name, items: [] })
                            groups.push(index.get(name))
                        }

                        index.get(name).items.push({ ...command, position })
                    })

                    return groups
                },

                /**
                 * The flat header/command list the templates render. Each
                 * command keeps its real type under `kind` so actions and
                 * events stay distinguishable from links.
                 */
                get flat() {
                    const out = []

                    for (const section of this.grouped) {
                        out.push({ type: 'header', group: section.group, id: 'h-' + section.group })

                        for (const item of section.items) {
                            out.push({ ...item, type: 'command', kind: item.type })
                        }
                    }

                    return out
                },

                previewEntries(command) {
                    if (!command) return []

                    if (command.preview && Object.keys(command.preview).length) {
                        return Object.entries(command.preview)
                    }

                    return [
                        [this.config.previewGroup, command.group],
                        [this.config.previewUrl, command.url],
                    ].filter(([, value]) => value)
                },

                /**
                 * Links stay real anchors, so middle-click and cmd-click keep
                 * working. Modifier-free clicks on an SPA panel use Livewire
                 * navigate instead of a full reload.
                 */
                handleClick(item, event) {
                    if (!item) return

                    const kind = item.kind ?? item.type

                    this.remember(item)

                    if (kind === 'link' && item.url) {
                        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                            setTimeout(() => this.close(), 0)

                            return
                        }

                        event.preventDefault()
                        this.close()
                        this.navigateTo(item.url, item.openInNewTab)

                        return
                    }

                    event.preventDefault()
                    this.run(item, event.metaKey || event.ctrlKey)
                },

                get selected() {
                    return this.results[this.selectedIndex] ?? null
                },

                get resultCount() {
                    return this.results.length
                },

                get isEmpty() {
                    return !this.isLoading && this.resultCount === 0 && !this.showHelp
                },

                storageKey() {
                    return `filament-command-palette:recents:${this.config.panelKey}`
                },

                pinStorageKey() {
                    return `filament-command-palette:pins:${this.config.panelKey}`
                },

                readPins() {
                    try {
                        const raw = window.localStorage.getItem(this.pinStorageKey())
                        const parsed = raw ? JSON.parse(raw) : []
                        return Array.isArray(parsed) ? parsed : []
                    } catch (error) {
                        return []
                    }
                },

                persistPins() {
                    try {
                        window.localStorage.setItem(this.pinStorageKey(), JSON.stringify(this.pins))
                    } catch (error) {
                        // Pins are optional when storage is unavailable.
                    }
                },

                pinSnapshot(command) {
                    if (command.type === 'link' && command.url) {
                        return {
                            id: command.id,
                            type: command.type,
                            label: command.label,
                            url: command.url,
                            group: command.group,
                            icon: command.icon,
                            openInNewTab: command.openInNewTab,
                            description: command.description,
                            keywords: command.keywords ?? [],
                            badge: command.badge,
                            preview: command.preview,
                            hasChildren: false,
                            drillable: Boolean(command.drillable),
                            resourceScope: command.resourceScope ?? null,
                            snapshot: true,
                        }
                    }

                    return { id: command.id }
                },

                togglePin(command) {
                    if (!this.config.pinsEnabled || !command) return

                    const existing = this.pins.findIndex((pin) => pin.id === command.id)

                    if (existing >= 0) {
                        this.pins.splice(existing, 1)
                    } else {
                        this.pins.unshift(this.pinSnapshot(command))
                        this.pins = this.pins.slice(0, this.config.maxPins)
                    }

                    this.persistPins()
                },

                resolvePins(source, contextIds) {
                    const byId = new Map(source.map((command) => [command.id, command]))
                    const out = []
                    const kept = []

                    for (const pin of this.pins) {
                        if (contextIds.has(pin.id)) {
                            kept.push(pin)
                            continue
                        }

                        const live = byId.get(pin.id)

                        if (live) {
                            out.push(this.decorate(live, [], { group: this.config.pinsGroup }))
                            kept.push(pin)
                            continue
                        }

                        if (pin.snapshot && pin.url) {
                            out.push(this.decorate(pin, [], { group: this.config.pinsGroup }))
                            kept.push(pin)
                        }
                    }

                    if (kept.length !== this.pins.length) {
                        this.pins = kept
                        this.persistPins()
                    }

                    return out.slice(0, this.config.maxPins)
                },

                bindPinShortcut() {
                    window.addEventListener('keydown', (event) => {
                        if (!this.isOpen || !this.config.pinsEnabled) return
                        if (!(event.metaKey || event.ctrlKey) || event.key.toLowerCase() !== 'p') return
                        if (event.shiftKey || event.altKey) return

                        event.preventDefault()
                        this.togglePin(this.selected)
                    })
                },

                readRecents() {
                    try {
                        const raw = window.localStorage.getItem(this.storageKey())
                        const parsed = raw ? JSON.parse(raw) : []
                        return Array.isArray(parsed) ? parsed : []
                    } catch (error) {
                        // Recents are optional when storage is unavailable.
                        return []
                    }
                },

                remember(command) {
                    if (!this.config.recentsEnabled || command.type === 'event') return

                    const now = Date.now()
                    const existing = this.recents.find((r) => r.id === command.id)

                    if (existing) {
                        existing.count += 1
                        existing.at = now
                    } else {
                        this.recents.unshift({ id: command.id, count: 1, at: now })
                    }

                    this.recents = this.recents
                        .sort((a, b) => this.rank(b) - this.rank(a))
                        .slice(0, 25)

                    try {
                        window.localStorage.setItem(this.storageKey(), JSON.stringify(this.recents))
                    } catch (error) {
                        // Nothing to do; ranking just will not persist.
                    }
                },

                rank(entry) {
                    const days = (Date.now() - entry.at) / 86400000
                    return entry.count * Math.exp(-days / 14)
                },

                frecency(id) {
                    if (!this.config.recentsEnabled) return 0
                    const entry = this.recents.find((r) => r.id === id)
                    return entry ? Math.min(this.rank(entry) * 12, 60) : 0
                },

                move(delta) {
                    const count = this.resultCount
                    if (!count) return

                    this.selectedIndex = (this.selectedIndex + delta + count) % count
                    this.scrollToSelected()
                },

                moveTo(index) {
                    if (!this.resultCount) return
                    this.selectedIndex = Math.max(0, Math.min(index, this.resultCount - 1))
                    this.scrollToSelected()
                },

                scrollToSelected() {
                    this.$nextTick(() => {
                        this.$refs.results
                            ?.querySelector(`[data-index="${this.selectedIndex}"]`)
                            ?.scrollIntoView({ block: 'nearest' })
                    })
                },

                optionId(index) {
                    return `${this.config.id}-option-${index}`
                },

                get activeDescendant() {
                    return this.resultCount ? this.optionId(this.selectedIndex) : null
                },

                /** Keeps Tab inside the dialog while it is open. */
                trapFocus(event) {
                    const focusable = this.$refs.dialog?.querySelectorAll(
                        'a[href], button:not([disabled]), input, [tabindex]:not([tabindex="-1"])'
                    )

                    if (!focusable || !focusable.length) return

                    const first = focusable[0]
                    const last = focusable[focusable.length - 1]

                    if (event.shiftKey && document.activeElement === first) {
                        event.preventDefault()
                        last.focus()
                    } else if (!event.shiftKey && document.activeElement === last) {
                        event.preventDefault()
                        first.focus()
                    }
                },

                select(index, event) {
                    const command = this.results[index]
                    if (!command) return

                    this.run(command, event?.metaKey || event?.ctrlKey)
                },

                enter(event) {
                    const command = this.selected
                    if (!command) return

                    event?.preventDefault()
                    this.run(command, event?.metaKey || event?.ctrlKey)
                },

                async run(command, newTab = false) {
                    if (!command) return

                    if (command.hasChildren || command.type === 'submenu') {
                        await this.enterSubmenu(command)
                        return
                    }

                    this.remember(command)

                    if (command.type === 'event') {
                        window.dispatchEvent(new CustomEvent(command.event, {
                            detail: command.eventPayload ?? {},
                        }))
                        this.close()
                        return
                    }

                    if (command.type === 'link' && command.url) {
                        this.close()
                        this.navigateTo(command.url, newTab || command.openInNewTab)

                        return
                    }

                    const context = this.contextPayload()

                    if (command.type === 'filamentAction') {
                        this.lastFocused = null
                        this.close()
                        try {
                            await this.$wire.mountPaletteAction(
                                command.id,
                                this.term,
                                context.pageClass,
                                context.recordKey,
                                context.parentId,
                            )
                        } catch (error) {
                            console.error('[command-palette] Could not mount the Filament action.', error)
                        }
                        return
                    }

                    if (command.type === 'mountAction' || command.mountAction) {
                        this.close()
                        try {
                            const result = await this.$wire.runCommand(
                                command.id,
                                this.term,
                                context.pageClass,
                                context.recordKey,
                                context.parentId,
                            )
                            if (result?.mountAction) {
                                this.triggerHostAction(result.mountAction, result.arguments ?? {})
                            }
                        } catch (error) {
                            console.error('[command-palette] Could not mount the action.', error)
                        }
                        return
                    }

                    // Actions are resolved and re-authorized on the server.
                    this.close()
                    this.$wire.runCommand(
                        command.id,
                        this.term,
                        context.pageClass,
                        context.recordKey,
                        context.parentId,
                    )
                },

                canDrill(command) {
                    return Boolean(command && (command.drillable || command.hasChildren || command.type === 'submenu'))
                },

                onTab(event) {
                    const command = this.selected

                    if (this.canDrill(command) && document.activeElement === this.$refs.searchInput) {
                        event.preventDefault()
                        this.enterSubmenu(command)
                        return
                    }

                    this.trapFocus(event)
                },

                onBackspace(event) {
                    if (this.query.length > 0 || !this.stack.length) return

                    event.preventDefault()
                    this.popStack()
                },

                triggerHostAction(name, args) {
                    window.dispatchEvent(new CustomEvent('filament-command-palette:trigger-action', {
                        detail: { name, arguments: args },
                    }))

                    if (!this.hostComponentId || typeof window.Livewire === 'undefined') return

                    try {
                        window.Livewire.find(this.hostComponentId)?.call('mountAction', name, args ?? {})
                    } catch (error) {
                        console.error('[command-palette] Host page did not accept mountAction.', error)
                    }
                },

                bindChords() {
                    if (!this.config.chordsEnabled) return

                    window.addEventListener('keydown', (event) => this.onChordKey(event))
                },

                onChordKey(event) {
                    if (!this.config.chordsEnabled || this.isOpen) return
                    if (event.metaKey || event.ctrlKey || event.altKey) return
                    if (this.isTypingTarget(event.target)) return

                    const key = event.key && event.key.length === 1 ? event.key.toLowerCase() : null
                    if (!key) return

                    if (this.pendingChord) {
                        event.preventDefault()
                        const chord = `${this.pendingChord} ${key}`
                        this.clearPendingChord()
                        this.runChord(chord)
                        return
                    }

                    if (!this.hasLoaded && /^[a-z0-9]$/.test(key)) {
                        this.load()
                    }

                    if (this.commandsHavePrefix(key)) {
                        this.pendingChord = key
                        this.chordTimer = setTimeout(() => this.clearPendingChord(), this.config.chordTimeout)
                    }
                },

                commandsHavePrefix(key) {
                    return this.commands.some((command) => typeof command.chord === 'string' && command.chord.startsWith(`${key} `))
                },

                async runChord(chord) {
                    if (!this.hasLoaded) await this.load()

                    const command = this.commands.find((item) => item.chord === chord)
                    if (!command || (command.requiresTableSelection && !this.hasTableSelection)) return

                    await this.run(command)
                },

                clearPendingChord() {
                    this.pendingChord = null
                    if (this.chordTimer) {
                        clearTimeout(this.chordTimer)
                        this.chordTimer = null
                    }
                },

                isTypingTarget(target) {
                    if (!target) return false

                    const tag = (target.tagName || '').toLowerCase()
                    if (['input', 'textarea', 'select'].includes(tag)) return true
                    if (target.isContentEditable) return true
                    if (typeof target.closest !== 'function') return false
                    if (target.closest('[contenteditable="true"]')) return true
                    if (target.closest('.fi-fo-field, .fi-input-wrp, .fi-sc-field')) return true

                    return false
                },

                shouldUseSpa(url) {
                    if (!this.config.spa || !url) return false
                    if (typeof Alpine === 'undefined' || typeof Alpine.navigate !== 'function') return false

                    try {
                        return new URL(url, window.location.href).origin === window.location.origin
                    } catch (error) {
                        return false
                    }
                },

                navigateTo(url, newTab = false) {
                    if (newTab) {
                        window.open(url, '_blank', 'noopener,noreferrer')
                        return
                    }

                    if (this.shouldUseSpa(url)) {
                        Alpine.navigate(url)
                        return
                    }

                    window.location.href = url
                },
            }))
        })

        // Theme commands, handled entirely in the browser.
        window.addEventListener('command-palette-set-theme', (event) => {
            const theme = event.detail?.theme ?? 'system'

            try {
                window.localStorage.setItem('theme', theme)
            } catch (error) {
                // Storage unavailable; the class change below still applies.
            }

            const dark = theme === 'dark'
                || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)

            document.documentElement.classList.toggle('fi-theme-dark', dark)
            document.documentElement.classList.toggle('dark', dark)

            window.dispatchEvent(new CustomEvent('theme-changed', { detail: theme }))
        })
    </script>
@endonce
