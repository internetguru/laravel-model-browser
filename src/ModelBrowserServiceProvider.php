<?php

namespace Internetguru\ModelBrowser;

use Illuminate\Support\ServiceProvider;
use Internetguru\ModelBrowser\Components\BaseModelBrowser;
use Internetguru\ModelBrowser\Components\TableModelBrowser;
use Livewire\Livewire;

class ModelBrowserServiceProvider extends ServiceProvider
{
    public function register()
    {
        //
    }

    public function boot()
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'model-browser');
        $this->loadTranslationsFrom(__DIR__ . '/../lang', 'model-browser');

        Livewire::component('base-model-browser', BaseModelBrowser::class);
        Livewire::component('table-model-browser', TableModelBrowser::class);

        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/model-browser'),
        ], 'views');
        $this->publishes([
            __DIR__ . '/../lang' => resource_path('lang/vendor/model-browser'),
        ], 'translations');
    }
}
