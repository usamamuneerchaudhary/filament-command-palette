<div class="fi-cp-footer">
    <div class="fi-cp-footer-hints">
        <span class="fi-cp-hint">
            <kbd class="fi-cp-kbd">&uparrow;</kbd><kbd class="fi-cp-kbd">&downarrow;</kbd>
            {{ __('command-palette::command-palette.modes.help.navigate') }}
        </span>

        <span class="fi-cp-hint">
            <kbd class="fi-cp-kbd">&crarr;</kbd>
            {{ __('command-palette::command-palette.modes.help.select') }}
        </span>

        <span class="fi-cp-hint fi-cp-hint--wide">
            <kbd class="fi-cp-kbd">tab</kbd>
            {{ __('command-palette::command-palette.modes.help.drill') }}
        </span>

        <span class="fi-cp-hint fi-cp-hint--wide">
            <kbd class="fi-cp-kbd">esc</kbd>
            <span x-text="stack.length ? @js(__('command-palette::command-palette.modes.help.back')) : @js(__('command-palette::command-palette.modes.help.close'))"></span>
        </span>
    </div>

    <span class="fi-cp-footer-modes" x-show="config.modesEnabled" x-cloak>
        {{ __('command-palette::command-palette.modes.hint') }}
    </span>
</div>
