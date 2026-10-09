<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HelpPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_help_page_is_public(): void
    {
        $this->get('/ajuda')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('help'));
    }
}
