@include('command-palette::livewire.partials.overlay-open', [
    'panelClass' => 'fi-cp-panel--sm',
    'variant' => 'groups',
])
    @include('command-palette::livewire.partials.search-input')
    @include('command-palette::livewire.partials.states')
    @include('command-palette::livewire.partials.results-list')
    @include('command-palette::livewire.partials.footer')
@include('command-palette::livewire.partials.overlay-close')
