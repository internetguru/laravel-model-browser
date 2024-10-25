<?php

namespace Tests\Components;

use Internetguru\ModelBrowser\Components\BaseModelBrowser;
use Livewire\Livewire;
use Tests\TestCase;

class BaseModelBrowserTest extends TestCase
{
    public function test_can_mount_with_default_values()
    {
        Livewire::test(BaseModelBrowser::class, [
            'model' => \App\Models\User::class,
        ])->assertSet('model', \App\Models\User::class)
            ->assertSet('modelMethod', '')
            ->assertSet('perPage', BaseModelBrowser::PER_PAGE_DEFAULT);

        Livewire::test(BaseModelBrowser::class, [
            'model' => '\App\Models\User@summary',
        ])->assertSet('model', '\App\Models\User')
            ->assertSet('modelMethod', 'summary')
            ->assertSet('perPage', BaseModelBrowser::PER_PAGE_DEFAULT);
    }
}
