<div>
    <button
        class="fullscreen btn btn-icon btn-primary"
        x-data="{
            fullscreen: false,
            toggleFullscreen: function() {
                this.fullscreen = ! this.fullscreen;
                this.$dispatch('fullscreen', { fullscreen: this.fullscreen });
            }
        }"
        x-on:click.stop="toggleFullscreen()"
        x-on:keydown.escape.document="toggleFullscreen()"
        x-on:fullscreen.window="$el.classList.toggle('active', $event.detail.fullscreen)"
    >
        <span x-show="! fullscreen"><i class="fa-solid fa-fw fa-expand pe-2"></i> @lang('model-browser::global.fullscreen')</span>
        <span x-show="fullscreen"><i class="fa-solid fa-fw fa-compress pe-2"></i> @lang('model-browser::global.fullscreen-exit')</span>
    </button>
</div>
