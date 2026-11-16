<?php

namespace App\Console\Commands;

use App\Actions\Lessons\GenerateNextReadings;
use App\Enums\LessonStatus;
use App\Models\Classroom;
use App\Support\ChurchCalendar;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ebd:generate-readings')]
#[Description('Escreve com IA a sequência de leituras da semana que vem, nas classes cuja lição atual continua e não tem leitura nova')]
class GenerateReadings extends Command
{
    public function handle(GenerateNextReadings $generate): int
    {
        $created = 0;

        foreach (Classroom::query()->active()->whereNotNull('current_lesson_id')->with('currentLesson', 'church')->get() as $classroom) {
            $lesson = $classroom->currentLesson;

            if ($lesson === null || $lesson->status !== LessonStatus::Published) {
                continue;
            }

            // A semana de amanhã: encerrado o encontro com "a lição continua",
            // a sequência nova já existe quando a segunda-feira chega.
            $created += ChurchCalendar::using($classroom->church, fn () => $generate->handle(
                $lesson,
                $lesson->readingWeek(ChurchCalendar::today()->addDay()->toDateString()),
            ));
        }

        $this->components->info("Leituras da semana: {$created} leitura(s) escrita(s).");

        return self::SUCCESS;
    }
}
