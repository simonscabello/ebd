<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Support\SessionKey;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.debug' => false]);
    }

    public function test_missing_lesson_shows_branded_404(): void
    {
        $this->get('/licoes/nao-existe')
            ->assertNotFound()
            ->assertInertia(fn (Assert $page) => $page
                ->component('error')
                ->where('status', 404)
                ->has('church.name'));
    }

    public function test_unknown_address_shows_branded_404(): void
    {
        $this->get('/endereco-que-nao-existe')
            ->assertNotFound()
            ->assertInertia(fn (Assert $page) => $page->component('error')->where('status', 404));
    }

    public function test_forbidden_shows_branded_403(): void
    {
        Route::middleware('web')->get('/_teste/403', fn () => abort(403));

        $this->get('/_teste/403')
            ->assertForbidden()
            ->assertInertia(fn (Assert $page) => $page->component('error')->where('status', 403));
    }

    public function test_expired_session_goes_back_with_a_warning(): void
    {
        Route::middleware('web')->post('/_teste/419', fn () => abort(419));

        $this->from('/biblioteca')
            ->post('/_teste/419')
            ->assertRedirect('/biblioteca')
            ->assertSessionHas(SessionKey::FLASH_DATA, fn (array $flash) => $flash['toast']['message'] === 'Sua sessão expirou. Tente de novo.');
    }

    public function test_json_requests_keep_json_errors(): void
    {
        $this->getJson('/endereco-que-nao-existe')
            ->assertNotFound()
            ->assertJsonStructure(['message']);
    }
}
