<?php

namespace App\Http\Controllers;

use App\Http\Resources\LessonResource;
use App\Models\Lesson;
use App\Support\PixCode;
use Inertia\Inertia;
use Inertia\Response;

/**
 * O site público: a apresentação do app em "/" para quem não entrou e a
 * página "Apoie a Didaché" (Pix). Quem está logado em "/" vê o app (HomeController).
 */
class SiteController extends Controller
{
    public function home(): Response
    {
        // Estudos de exemplo: os publicados e públicos da biblioteca oficial
        // (o que o visitante enxerga, ver Church::idsVisibleTo).
        $examples = Lesson::query()
            ->visibleTo(null)
            ->with(['series', 'classroom'])
            ->orderByDesc('id')
            ->limit(2)
            ->get();

        return Inertia::render('site/home', [
            'examples' => LessonResource::collection($examples),
            'pixKey' => (string) config('brand.pix.key'),
        ])->withViewData(['meta' => [
            'title' => config('brand.name').' · '.config('brand.tagline'),
            'description' => 'Estudo bíblico da semana no celular para a classe da sua igreja: o aluno estuda um pouco por dia, o professor acompanha a turma e faz a chamada. Gratuito.',
            'url' => route('home'),
        ]]);
    }

    public function support(): Response
    {
        $payload = PixCode::fromConfig();

        return Inertia::render('site/support', [
            'pixKey' => (string) config('brand.pix.key'),
            'pixPayload' => $payload,
            'pixQr' => 'data:image/svg+xml;base64,'.base64_encode(PixCode::qrSvg($payload)),
        ])->withViewData(['meta' => [
            'title' => 'Apoie a '.config('brand.name'),
            'description' => 'A '.config('brand.name').' é gratuita para as igrejas e mantida por ofertas voluntárias via Pix. Qualquer valor ajuda.',
            'url' => route('support'),
        ]]);
    }
}
