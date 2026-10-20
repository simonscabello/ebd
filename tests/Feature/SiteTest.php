<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\User;
use App\Support\OfficialLibrary;
use App\Support\PixCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Site público: "/" para visitantes, "Apoie" com o Pix e a biblioteca do
 * visitante só com os estudos de exemplo (biblioteca oficial).
 */
class SiteTest extends TestCase
{
    use RefreshDatabase;

    private const PIX_KEY = '3d43655c-6fd3-456d-aded-5e05d7bf15ac';

    public function test_guest_sees_the_site_with_official_example_studies(): void
    {
        $official = OfficialLibrary::classroom();
        Lesson::factory()->for($official)->published()->create(['title' => 'Exemplo oficial']);
        Lesson::factory()->for($official)->create(['title' => 'Rascunho oficial']);
        Lesson::factory()->for(Classroom::factory()->create())->published()->create(['title' => 'Da igreja']);

        $this->get('/')
            ->assertOk()
            ->assertSee('<title>Didaché · Ensino bíblico em comunidade</title>', false)
            ->assertSee('<meta property="og:title" content="Didaché · Ensino bíblico em comunidade">', false)
            ->assertSee('<meta property="og:url" content="'.url('/').'">', false)
            ->assertSee('name="description" content="Estudo bíblico da semana', false)
            ->assertInertia(fn (Assert $page) => $page
                ->component('site/home')
                ->where('pixKey', self::PIX_KEY)
                ->has('examples', 1)
                ->where('examples.0.title', 'Exemplo oficial'));
    }

    public function test_examples_are_empty_without_official_studies(): void
    {
        Lesson::factory()->for(Classroom::factory()->create())->published()->create();

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->component('site/home')
            ->has('examples', 0));
    }

    public function test_logged_in_user_still_gets_the_app(): void
    {
        $classroom = Classroom::factory()->create();
        $student = User::factory()->studentOf($classroom)->create();

        $this->actingAs($student)->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('home')->where('isMember', true));
    }

    public function test_support_page_shows_the_key_and_a_valid_static_br_code(): void
    {
        $response = $this->get('/apoie')
            ->assertOk()
            ->assertSee('<title>Apoie a Didaché</title>', false)
            ->assertSee('<meta property="og:title" content="Apoie a Didaché">', false);

        $props = $response->viewData('page')['props'];
        $this->assertSame('site/support', $response->viewData('page')['component']);
        $this->assertSame(self::PIX_KEY, $props['pixKey']);
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $props['pixQr']);
        $this->assertStringContainsString('<svg', base64_decode(substr($props['pixQr'], strlen('data:image/svg+xml;base64,'))));

        $payload = $props['pixPayload'];
        $fields = $this->parseTlv($payload);

        $this->assertSame('01', $fields['00']);
        $this->assertSame('11', $fields['01']); // estático
        $this->assertSame(['00' => 'br.gov.bcb.pix', '01' => self::PIX_KEY], $this->parseTlv($fields['26']));
        $this->assertSame('0000', $fields['52']);
        $this->assertSame('986', $fields['53']);
        $this->assertArrayNotHasKey('54', $fields); // sem valor fixo
        $this->assertSame('BR', $fields['58']);
        $this->assertSame('Didache', $fields['59']);
        $this->assertSame('SAO PAULO', $fields['60']);
        $this->assertSame(['05' => '***'], $this->parseTlv($fields['62']));

        // CRC16-CCITT-FALSE sobre tudo até "6304", calculado aqui de forma independente.
        $this->assertSame(substr($payload, -8, 4), '6304');
        $this->assertSame($this->crc(substr($payload, 0, -4)), substr($payload, -4));
        $this->assertSame(
            '00020101021126580014br.gov.bcb.pix0136'.self::PIX_KEY.'5204000053039865802BR5907Didache6009SAO PAULO62070503***63045C82',
            $payload,
        );
    }

    public function test_pix_crc_matches_the_standard_check_value(): void
    {
        $this->assertSame('29B1', PixCode::crc16('123456789'));
    }

    public function test_pix_name_and_city_are_cleaned_and_cut(): void
    {
        $fields = $this->parseTlv(PixCode::payload('chave', 'Didaché — Ensino bíblico em comunidade', 'São José dos Campos'));

        $this->assertSame('Didache  Ensino biblico e', $fields['59']);
        $this->assertSame('Sao Jose dos Ca', $fields['60']);
    }

    public function test_logged_in_user_can_open_the_support_page(): void
    {
        $this->actingAs(User::factory()->create())->get('/apoie')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('site/support'));
    }

    public function test_anonymous_library_shows_only_official_example_studies(): void
    {
        $official = OfficialLibrary::classroom();
        Lesson::factory()->for($official)->published()->create(['title' => 'Exemplo oficial']);
        Lesson::factory()->for($official)->published()->membersOnly()->create(['title' => 'Oficial restrito']);

        $classroom = Classroom::factory()->create();
        Lesson::factory()->for($classroom)->published()->create(['title' => 'Da igreja']);
        $member = User::factory()->studentOf($classroom)->create();

        $this->get('/biblioteca')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('results.data', 1)
            ->where('results.data.0.title', 'Exemplo oficial'));

        // Quem entrou continua vendo a própria igreja.
        $this->actingAs($member)->get('/biblioteca')->assertInertia(fn (Assert $page) => $page
            ->has('results.data', 1)
            ->where('results.data.0.title', 'Da igreja'));
    }

    public function test_public_lesson_link_still_opens_for_guests(): void
    {
        $lesson = Lesson::factory()->for(Classroom::factory()->create())->published()->create(['title' => 'Link do WhatsApp']);

        $this->get('/licoes/'.$lesson->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('lessons/show')
                ->where('lesson.title', 'Link do WhatsApp'));
    }

    /**
     * @return array<string, string>
     */
    private function parseTlv(string $data): array
    {
        $fields = [];

        for ($i = 0; $i < strlen($data);) {
            $id = substr($data, $i, 2);
            $length = (int) substr($data, $i + 2, 2);
            $fields[$id] = substr($data, $i + 4, $length);
            $i += 4 + $length;
        }

        return $fields;
    }

    private function crc(string $data): string
    {
        $crc = 0xFFFF;

        for ($i = 0; $i < strlen($data); $i++) {
            $crc ^= ord($data[$i]) << 8;

            for ($bit = 0; $bit < 8; $bit++) {
                $crc = $crc & 0x8000 ? ($crc << 1) ^ 0x1021 : $crc << 1;
                $crc &= 0xFFFF;
            }
        }

        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }
}
