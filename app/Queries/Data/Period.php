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
        $series = $series !== null ? self::started($series) : null;

        return $series !== null ? self::forSeries($series) : self::lastMonths(3);
    }

    /**
     * A série que já começou. Na semana antes do primeiro domingo de uma série
     * nova, os números continuam na anterior (a do último domingo com lição de
     * outra série); sem anterior, null.
     */
    public static function started(Series $series): ?Series
    {
        $today = ChurchCalendar::todayString();

        if (self::forSeries($series)->from <= $today) {
            return $series;
        }

        return ClassMeeting::query()
            ->active()
            ->where('classroom_id', $series->classroom_id)
            ->whereDate('held_on', '<=', $today)
            ->whereHas('lesson', fn ($query) => $query->whereNotNull('series_id')->where('series_id', '!=', $series->id))
            ->with('lesson.series')
            ->latest('held_on')
            ->first()
            ?->lesson
            ?->series;
    }

    public function contains(string $date): bool
    {
        return ($this->from === null || $date >= $this->from) && $date <= $this->to;
    }
}
