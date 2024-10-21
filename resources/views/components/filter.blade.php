<span class="position-relative">
    <x-ig::input type="text" name="filter" wire:model.live.debounce.400="filter">@lang('model-browser::global.filter')</x-ig::input>
    @if ($filter)
        <i class="fas fa-fw fa-close position-absolute top-50 end-0 translate-middle-y p-2 z-1" style="cursor: pointer;" wire:click="$set('filter', '')"></i>
    @endif
</span>
