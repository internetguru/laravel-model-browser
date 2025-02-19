<div>
    <button
        class="fullscreen btn btn-icon btn-white"
        x-data="{
            fullscreen: false,
            toggleFullscreen: function() {
                this.fullscreen = ! this.fullscreen;
                this.$dispatch('fullscreen', { fullscreen: this.fullscreen });
            }
        }"
        x-on:click.stop="toggleFullscreen()"
        x-on:keydown.escape.document="fullscreen = true; toggleFullscreen()"
        x-on:fullscreen.window="$el.classList.toggle('active', $event.detail.fullscreen)"
    >
        <span x-show="! fullscreen" title="{{ __('model-browser::global.fullscreen') }}"><i class="fa-solid fa-fw fa-expand"></i></span>
        <span x-show="fullscreen" title="{{ __('model-browser::global.fullscreen-exit') }}"><i class="fa-solid fa-fw fa-compress"></i></span>
    </button>
</div>
