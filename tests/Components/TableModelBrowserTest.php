<?php

namespace Tests\Components;

use Internetguru\ModelBrowser\Components\TableModelBrowser;
use Livewire\Livewire;
use Tests\TestCase;

class TableModelBrowserTest extends TestCase
{
    public function test_can_mount_with_default_values()
    {
        Livewire::test(TableModelBrowser::class, [
            'model' => \App\Models\User::class,
        ])->assertSet('model', \App\Models\User::class)
            ->assertSet('lightDarkStep', 1);
    }

    public function test_renders_correct_view()
    {
        Livewire::test(TableModelBrowser::class, [
            'model' => \App\Models\User::class,
        ])->assertViewIs('model-browser::livewire.table');
    }
}
