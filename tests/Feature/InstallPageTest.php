<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InstallPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_install_tutorial_is_public(): void
    {
        $this->get('/instalar')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('install'));
    }
}
