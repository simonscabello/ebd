<?php

namespace Tests\Feature\Settings;

use App\Models\Classroom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_page_lists_classrooms_with_the_role(): void
    {
        $jovens = Classroom::factory()->create(['name' => 'Jovens']);
        $adultos = Classroom::factory()->create(['name' => 'Adultos']);
        $user = User::factory()->teacherOf($jovens)->studentOf($adultos)->create();

        $this->actingAs($user)
            ->get('/conta')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/index')
                ->has('classrooms', 2)
                ->where('classrooms', fn ($rows) => collect($rows)->pluck('role_label', 'name')->sortKeys()->all()
                    === ['Adultos' => 'Aluno', 'Jovens' => 'Professor']),
            );
    }

    public function test_old_appearance_page_redirects_to_the_account_page(): void
    {
        $this->actingAs(User::factory()->create())->get('/conta/aparencia')->assertRedirect('/conta');
    }

    public function test_user_uploads_replaces_and_removes_the_photo(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/conta/foto', ['avatar' => UploadedFile::fake()->image('eu.jpg', 512, 512)])
            ->assertSessionHasNoErrors();

        $first = $user->refresh()->avatar_path;
        $this->assertNotNull($first);
        Storage::disk('public')->assertExists($first);

        $this->actingAs($user)
            ->get('/conta')
            ->assertInertia(fn (Assert $page) => $page->where('auth.user.avatar_url', Storage::disk('public')->url($first)));

        $this->actingAs($user)->post('/conta/foto', ['avatar' => UploadedFile::fake()->image('eu.webp', 512, 512)]);

        $second = $user->refresh()->avatar_path;
        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);

        $this->actingAs($user)->delete('/conta/foto')->assertSessionHasNoErrors();

        $this->assertNull($user->refresh()->avatar_path);
        Storage::disk('public')->assertMissing($second);
    }

    public function test_photo_must_be_an_image(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/conta/foto', ['avatar' => UploadedFile::fake()->create('virus.pdf', 100, 'application/pdf')])
            ->assertSessionHasErrors('avatar');

        $this->assertNull($user->refresh()->avatar_path);
    }

    public function test_photo_is_deleted_with_the_account(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)->post('/conta/foto', ['avatar' => UploadedFile::fake()->image('eu.png')]);
        $path = $user->refresh()->avatar_path;

        $this->actingAs($user)->delete('/conta/perfil', ['password' => 'password'])->assertSessionHasNoErrors();

        Storage::disk('public')->assertMissing($path);
    }
}
