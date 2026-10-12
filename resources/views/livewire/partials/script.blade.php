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
                selectedIndex: 0,
                lastFocused: null,
                searchToken: 0,
                searchTimer: null,

                init() {
                    this.recents = this.readRecents()
                    this.$watch('query', () => {
                        this.selectedIndex = 0
                        this.parseMode()
                        this.queueRecordSearch()
                    })
                },

                // ----- opening and closing -------------------------------------

                async open() {
                    if (this.isOpen) return

                    this.lastFocused = document.activeElement
                    this.isOpen = true
                    this.lockScroll(true)

                    await this.load()

                    this.$nextTick(() => {
                        this.selectedIndex = 0
                        this.$refs.searchInput?.focus()
                        this.$refs.searchInput?.select()
                    })
                },

                close() {
                    if (!this.isOpen) return

                    this.isOpen = false
                    this.query = ''
                    this.mode = null
                    this.records = []
                    this.selectedIndex = 0
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
                    if (this.hasLoaded || this.isLoading) return

                    this.isLoading = true
                    this.loadFailed = false

                    try {
                        const response = await this.$wire.loadCommands()
                        this.commands = response?.commands ?? []
                        this.icons = response?.icons ?? {}
                        this.hasLoaded = true
                    } catch (error) {
                        this.loadFailed = true
                        console.error('[command-palette] Could not load commands.', error)
                    } finally {
                        this.isLoading = false
                    }
                },

                // ----- modes ---------------------------------------------------

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

                // ----- record search -------------------------------------------

                queueRecordSearch() {
                    if (!this.config.recordSearch || this.mode === 'actions' || this.mode === 'help') {
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
                            const results = await this.$wire.searchRecords(term)
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

                // ----- results -------------------------------------------------

                get pool() {
                    if (this.mode === 'records') return this.records
                    if (this.mode === 'actions') {
                        return this.commands.filter((c) => c.type !== 'link' || c.group === this.config.actionsGroup)
                    }
                    return this.commands.concat(this.records)
                },

                get results() {
                    const term = this.term

                    if (!term) {
                        return this.defaultResults()
                    }

                    const scored = []

                    for (const command of this.pool) {
                        const match = this.scoreCommand(command, term)
                        if (match) scored.push(match)
                    }

                    scored.sort((a, b) => b.score - a.score)

                    return scored.slice(0, this.config.maxResults)
                },

                defaultResults() {
                    // `#` on its own has nothing to show until something is
                    // typed — record results only exist once a query runs.
                    if (this.mode === 'records') return []

                    // `>` narrows to actions even before a term is typed.
                    const source = this.mode === 'actions' ? this.pool : this.commands

                    const recentIds = this.recents.map((r) => r.id)
                    const byId = new Map(source.map((c) => [c.id, c]))

                    const recent = this.config.recentsEnabled
                        ? recentIds
                            .map((id) => byId.get(id))
                            .filter(Boolean)
                            .slice(0, this.config.maxRecents)
                            .map((c) => this.decorate(c, [], { group: this.config.recentsGroup }))
                        : []

                    const recentSet = new Set(recent.map((c) => c.id))

                    const rest = source
                        .filter((c) => !recentSet.has(c.id))
                        .sort((a, b) => (b.priority ?? 0) - (a.priority ?? 0))
                        .slice(0, Math.max(0, this.config.maxResults - recent.length))
                        .map((c) => this.decorate(c, []))

                    return recent.concat(rest)
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

                // ----- frecency ------------------------------------------------

                storageKey() {
                    return `filament-command-palette:recents:${this.config.panelKey}`
                },

                readRecents() {
                    try {
                        const raw = window.localStorage.getItem(this.storageKey())
                        const parsed = raw ? JSON.parse(raw) : []
                        return Array.isArray(parsed) ? parsed : []
                    } catch (error) {
                        // Private browsing, disabled storage — recents are optional.
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

                // ----- keyboard ------------------------------------------------

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

                // ----- running -------------------------------------------------

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

                run(command, newTab = false) {
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

                    // Actions are resolved and re-authorized on the server.
                    this.close()
                    this.$wire.runCommand(command.id, this.term)
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
