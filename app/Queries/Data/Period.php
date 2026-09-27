<?php

namespace App\Queries\Data;

use App\Models\ClassMeeting;
use App\Models\Series;
use App\Support\ChurchCalendar;

/**
 * Intervalo de domingos usado nos números da classe (Y-m-d; $from null = desde
 * sempre). O fim nunca passa de hoje: domingo futuro não tem chamada.
 */
final readonly class Period
{
    public function __construct(
        public ?string $from,
        public string $to,
        public string $label,
        public ?int $seriesId = null,
    ) {}

    public static function allTime(): self
    {
        return new self(null, ChurchCalendar::todayString(), 'Todos os domingos');
    }

    public static function lastMonths(int $months): self
    {
        $today = ChurchCalendar::today();

        return new self($today->subMonths($months)->toDateString(), $today->toDateString(), "Últimos {$months} meses");
    }

    /**
     * Uma série (revista). Sem datas cadastradas, vale do primeiro ao último
     * domingo das lições dela; entram todos os domingos da classe no intervalo.
     */
    public static function forSeries(Series $series): self
    {
        $today = ChurchCalendar::todayString();

        /** @var object{first: string|null, last: string|null}|null $range */
        $range = ClassMeeting::query()
            ->active()
            ->whereHas('lesson', fn ($query) => $query->whereBelongsTo($series))
            ->selectRaw('MIN(held_on) AS first, MAX(held_on) AS last')
            ->toBase()
            ->first();

        $from = $series->starts_on?->toDateString() ?? ($range?->first !== null ? substr($range->first, 0, 10) : $today);
        $to = $series->ends_on?->toDateString() ?? ($range?->last !== null ? substr($range->last, 0, 10) : $today);

        return new self($from, min($to, $today), $series->title, $series->id);
    }

    /**
     * Padrão da classe: a série da lição da semana; sem série, os últimos 3 meses.
     */
    public static function current(?Series $series): self
    {
        return $series !== null ? self::forSeries($series) : self::lastMonths(3);
    }

    public function contains(string $date): bool
    {
        return ($this->from === null || $date >= $this->from) && $date <= $this->to;
    }
}
