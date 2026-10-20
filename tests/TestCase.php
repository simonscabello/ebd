<?php

namespace Tests;

use App\Support\ChurchCalendar;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // O fuso em contexto é estático: nada passa de um teste para outro.
        ChurchCalendar::setTimezone(null);

        // Nenhum teste fala com serviço de fora (OpenAI, push, pwned
        // passwords): quem precisa usa Http::fake.
        Http::preventStrayRequests();
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
