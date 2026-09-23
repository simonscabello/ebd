<?php

namespace Database\Seeders;

use App\Enums\LessonStatus;
use App\Enums\LessonVisibility;
use App\Enums\MaterialType;
use App\Enums\Weekday;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\Series;
use App\Models\User;
use App\Support\ChurchCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Séries e lições de exemplo com datas relativas a "hoje", para que a home
 * sempre mostre uma próxima aula e a biblioteca tenha histórico.
 */
class LessonContentSeeder extends Seeder
{
    private CarbonImmutable $nextSunday;

    public function run(): void
    {
        $this->nextSunday = ChurchCalendar::nextSunday();

        $this->seedJovens();
        $this->seedAdultos();
    }

    private function seedJovens(): void
    {
        $classroom = Classroom::query()->where('slug', 'jovens')->firstOrFail();
        $teacher = User::query()->where('email', 'professor@ebd.test')->firstOrFail();

        $series = $this->series($classroom, 'Jornada dos Milagres de Jesus', 'jornada-dos-milagres-de-jesus',
            'Uma caminhada pelos sinais realizados por Jesus nos Evangelhos: o que eles revelam sobre quem Ele é e o que significa segui-lo.',
            $this->sunday(-4), $this->sunday(4));

        $this->pastLesson($series, $teacher, -4, 'Água em Vinho: o Primeiro Sinal', 'João 2:1-11',
            'Nas bodas de Caná, Jesus manifesta sua glória e os discípulos creem nele. O primeiro sinal aponta para a alegria da nova aliança.',
            ['O que a atitude de Maria ("Façam tudo o que ele mandar") ensina sobre confiança?', 'Por que João chama os milagres de "sinais"?']);

        $this->pastLesson($series, $teacher, -3, 'A Cura do Paralítico de Cafarnaum', 'Marcos 2:1-12',
            'Quatro amigos abrem o telhado para levar alguém até Jesus. Antes de curar o corpo, Jesus perdoa pecados e revela sua autoridade.',
            ['Quem são as pessoas que você tem levado até Jesus?', 'Por que Jesus perdoa os pecados antes de curar?']);

        $this->pastLesson($series, $teacher, -2, 'Jesus Acalma a Tempestade', 'Marcos 4:35-41',
            'No meio da tempestade os discípulos perguntam se Jesus se importa. A resposta vem com autoridade sobre o vento e o mar.',
            ['"Mestre, não te importas que pereçamos?" Você já fez essa pergunta?', 'Qual a diferença entre ter medo da tempestade e temer ao Senhor?']);

        $this->pastLesson($series, $teacher, -1, 'A Multiplicação dos Pães', 'João 6:1-14',
            'Cinco pães e dois peixes nas mãos de Jesus alimentam uma multidão. O pouco entregue a Ele se torna suficiente.',
            ['O que você tem nas mãos que pode ser entregue a Jesus?', 'Por que Jesus mandou recolher os pedaços que sobraram?']);

        $this->santidadeDeDeus($series, $teacher);

        $draft = new Lesson([
            'title' => 'A Cura do Leproso',
            'summary' => 'Jesus toca quem ninguém tocava.',
            'scheduled_for' => $this->sunday(1)->toDateString(),
            'bible_reference' => 'Lucas 5:12-16',
            'content' => "## Introdução\n\n(rascunho em preparação)",
            'visibility' => LessonVisibility::Public,
        ]);
        $this->saveLesson($draft, $series, $teacher, 'a-cura-do-leproso', LessonStatus::Draft);
    }

    private function santidadeDeDeus(Series $series, User $teacher): void
    {
        $lesson = new Lesson([
            'title' => 'A Santidade de Deus',
            'summary' => 'Diante do milagre da pesca, Pedro reconhece a santidade de Jesus e a própria condição de pecador. O Senhor, porém, não o afasta: transforma o medo em chamado.',
            'scheduled_for' => $this->nextSunday->toDateString(),
            'bible_reference' => 'Lucas 5:1–11',
            'bible_text' => "\"Senhor, afasta-te de mim, porque sou pecador.\" (v. 8)\n\n\"Não temas; de agora em diante serás pescador de homens.\" (v. 10)",
            'content' => <<<'MD'
## Introdução

Pedro conhecia o mar da Galileia melhor do que ninguém. Tinha passado a noite inteira trabalhando e não havia pescado nada. Quando Jesus manda lançar as redes outra vez, tudo em Pedro diz que não vai dar certo — e mesmo assim ele obedece: *"sob a tua palavra lançarei as redes"* (v. 5).

O resultado é uma pesca tão grande que as redes começam a se romper. Mas o mais surpreendente não é o milagre: é a reação de Pedro.

## O contexto

Lucas situa o episódio no início do ministério de Jesus. A multidão aperta Jesus para ouvir a Palavra de Deus, e Ele usa o barco de Pedro como púlpito. Antes de ser um lugar de milagre, o barco foi lugar de ensino.

- Jesus entra no barco de Pedro **sem pedir licença ao trabalho** de Pedro, mas pedindo a Pedro.
- Pedro é um profissional experiente: a ordem de Jesus contraria sua experiência.
- A obediência vem antes da compreensão.

## A santidade revelada

Ao ver a pesca, Pedro não comemora o lucro. Ele cai aos pés de Jesus e diz: *"Senhor, afasta-te de mim, porque sou pecador"* (v. 8).

Pedro percebe que está diante de alguém que não é apenas um bom mestre. A mesma reação aparece em Isaías diante do trono de Deus (Isaías 6:5): quando a santidade de Deus se aproxima, a nossa condição fica exposta.

> A santidade de Deus não é apenas a ausência de pecado. É a perfeição de quem Ele é — e isso nos coloca, com humildade, no nosso devido lugar.

## Da prostração ao chamado

Jesus não se afasta. Ele responde: *"Não temas; de agora em diante serás pescador de homens"* (v. 10).

O encontro com a santidade de Deus poderia terminar em condenação, mas termina em graça e propósito. Pedro, Tiago e João deixam tudo e seguem Jesus (v. 11).

## Para a nossa vida

1. **Reverência:** aproximar-se de Deus com seriedade, sem banalizar sua presença.
2. **Obediência:** confiar na Palavra mesmo quando ela contraria a nossa experiência.
3. **Esperança:** reconhecer o pecado não é o fim da história; em Cristo, é o começo de um chamado.
MD,
            'teacher_notes' => <<<'MD'
- **Abertura (5 min):** perguntar quem já passou uma "noite inteira sem pescar nada" em alguma área da vida.
- **Leitura (5 min):** pedir a um aluno para ler Lucas 5:1–11 em voz alta.
- **Discussão (25 min):** usar as perguntas 1 e 3 com mais tempo; a 5 pode ficar para casa.
- Ligar com Isaías 6 (leitura de segunda-feira) — muitos devem ter lido.
- **Encerramento:** oração pedindo reverência e disposição para obedecer.
- Avisar que na próxima semana veremos a cura do leproso (Lucas 5:12-16).
MD,
            'visibility' => LessonVisibility::Public,
        ]);

        $this->saveLesson($lesson, $series, $teacher, 'a-santidade-de-deus', LessonStatus::Published);

        $this->readings($lesson, [
            [Weekday::Monday, 'Isaías 6:1-8', 'A visão de Isaías: "Santo, santo, santo". Compare a reação de Isaías com a de Pedro.'],
            [Weekday::Tuesday, 'Êxodo 3:1-6', 'Moisés diante da sarça: "tira as sandálias, o lugar é santo".'],
            [Weekday::Wednesday, 'Salmo 99', 'O Senhor reina — Ele é santo. Leia em voz alta, se puder.'],
            [Weekday::Thursday, 'Lucas 5:1-11', 'Texto base. Leia duas vezes, com calma, e anote o que mais chamou sua atenção.'],
            [Weekday::Friday, '1 Pedro 1:13-16', 'Anos depois, o próprio Pedro escreve: "sede santos".'],
            [Weekday::Saturday, 'Hebreus 12:14, 28-29', 'Prepare o coração para o domingo.'],
        ]);

        $lesson->questions()->createMany([
            ['body' => 'Por que Pedro pede que Jesus se afaste dele após reconhecer quem está diante dele?'],
            ['body' => 'Pedro obedeceu mesmo achando que não daria certo (v. 5). Em que área da sua vida Deus tem pedido obediência antes do entendimento?'],
            ['body' => 'O que a reação de Pedro ensina sobre a relação entre a santidade de Deus e a consciência do pecado?'],
            ['body' => 'Jesus responde ao medo de Pedro com um chamado: "Não temas". Como isso muda a forma como nos aproximamos de Deus?'],
            ['body' => 'O que significa, na prática, "deixar tudo" para seguir Jesus hoje?'],
        ]);

        $this->fileMaterial($lesson, MaterialType::Pdf, 'Lição 5 — A Santidade de Deus', 'Material da revista para estudo durante a semana.', 'licao-05-a-santidade-de-deus.pdf', [
            'Jornada dos Milagres de Jesus',
            'Lição 5 - A Santidade de Deus',
            'Texto base: Lucas 5:1-11',
            '',
            '(PDF de exemplo gerado pelo seeder)',
        ], primary: true);

        $this->fileMaterial($lesson, MaterialType::Pdf, 'Roteiro de estudo pessoal', 'Uma página para imprimir e anotar durante a semana.', 'roteiro-de-estudo.pdf', [
            'Roteiro de estudo pessoal',
            '1. Leia Lucas 5:1-11',
            '2. Anote as palavras que se repetem',
            '3. Responda as perguntas para reflexão',
        ]);

        $lesson->materials()->createMany([
            [
                'type' => MaterialType::Video,
                'title' => 'Vídeo: o mar da Galileia nos tempos de Jesus',
                'description' => 'Contexto histórico sobre a pesca no primeiro século (link fictício).',
                'url' => 'https://example.com/videos/mar-da-galileia',
            ],
            [
                'type' => MaterialType::Audio,
                'title' => 'Áudio: meditação em Isaías 6',
                'description' => 'Reflexão de 12 minutos para ouvir no caminho (link fictício).',
                'url' => 'https://example.com/audios/meditacao-isaias-6.mp3',
            ],
            [
                'type' => MaterialType::Link,
                'title' => 'Mapa: a região do lago de Genesaré',
                'url' => 'https://example.com/mapas/genesare',
            ],
            [
                'type' => MaterialType::Reference,
                'title' => 'O Conhecimento do Santo — A. W. Tozer',
                'description' => 'Capítulo 21: "A santidade de Deus". Leitura curta e profunda.',
            ],
            [
                'type' => MaterialType::Reference,
                'title' => 'A Santidade de Deus — R. C. Sproul',
                'description' => 'Para quem quiser aprofundar: capítulos 1 a 3.',
            ],
        ]);
    }

    private function seedAdultos(): void
    {
        $classroom = Classroom::query()->where('slug', 'adultos')->firstOrFail();
        $teacher = User::query()->where('email', 'professora@ebd.test')->firstOrFail();

        $joao = $this->series($classroom, 'Evangelho de João: os "Eu Sou"', 'evangelho-de-joao-eu-sou',
            'As sete declarações "Eu Sou" de Jesus no Evangelho de João.',
            $this->nextSunday->subYear()->subWeeks(2), $this->nextSunday->subYear());

        $this->pastLesson($joao, $teacher, -54, 'Eu Sou o Pão da Vida', 'João 6:35-51',
            'Depois de alimentar a multidão, Jesus se apresenta como o pão que desce do céu e sacia de verdade.',
            ['O que você tem buscado para saciar a fome da alma?']);

        $this->pastLesson($joao, $teacher, -53, 'Eu Sou a Luz do Mundo', 'João 8:12-20',
            'Quem segue a Jesus não anda em trevas. A luz revela, orienta e aquece.',
            ['Que áreas da sua vida precisam ser iluminadas pela Palavra?']);

        $filipenses = $this->series($classroom, 'Filipenses: Alegria em Cristo', 'filipenses-alegria-em-cristo',
            'Uma carta escrita da prisão que fala de alegria do começo ao fim.',
            $this->sunday(-2), $this->sunday(3));

        $this->pastLesson($filipenses, $teacher, -2, 'Alegria em Toda Circunstância', 'Filipenses 1:1-11',
            'Paulo ora com alegria pelos filipenses, confiante de que Deus completará a boa obra que começou.',
            ['Pelo que você é grato quando pensa nos irmãos da igreja?', 'O que significa "aquele que começou a boa obra a completará"?']);

        $restricted = $this->pastLesson($filipenses, $teacher, -1, 'Viver é Cristo', 'Filipenses 1:12-30',
            'Mesmo preso, Paulo vê o evangelho avançar. Para ele, viver é Cristo e morrer é lucro.',
            ['O que precisaria mudar para você dizer "para mim o viver é Cristo"?']);
        $restricted->forceFill(['visibility' => LessonVisibility::Members])->save();

        $next = new Lesson([
            'title' => 'O Exemplo de Cristo',
            'summary' => 'Humildade não é pensar menos de si, mas pensar mais nos outros. Cristo se esvaziou e foi exaltado.',
            'scheduled_for' => $this->nextSunday->toDateString(),
            'bible_reference' => 'Filipenses 2:1-11',
            'content' => "## Introdução\n\nO hino de Filipenses 2 é um dos textos mais antigos sobre quem é Jesus.\n\n## O mesmo sentimento\n\nPaulo pede unidade, humildade e cuidado mútuo.\n\n## O caminho da cruz\n\nCristo, sendo Deus, não se apegou à sua posição: esvaziou-se, fez-se servo e obedeceu até a morte.",
            'teacher_notes' => '- Dividir a turma em duplas para ler os versos 5 a 8.',
            'visibility' => LessonVisibility::Public,
        ]);
        $this->saveLesson($next, $filipenses, $teacher, 'o-exemplo-de-cristo', LessonStatus::Published);

        $this->readings($next, [
            [Weekday::Monday, 'Filipenses 2:1-4', 'O mesmo sentimento e o mesmo amor.'],
            [Weekday::Wednesday, 'Filipenses 2:5-11', 'Leia devagar: é um hino.'],
            [Weekday::Friday, 'João 13:1-17', 'Jesus lava os pés dos discípulos.'],
        ]);
        $next->questions()->createMany([
            ['body' => 'Em que situações é mais difícil considerar os outros superiores a nós mesmos?'],
            ['body' => 'O que o "esvaziar-se" de Cristo revela sobre o caráter de Deus?'],
        ]);
        $next->materials()->create([
            'type' => MaterialType::Reference,
            'title' => 'Comentário de Filipenses — John Stott',
            'description' => 'Série "A Bíblia Fala Hoje".',
        ]);
    }

    private function series(Classroom $classroom, string $title, string $slug, string $description, CarbonImmutable $startsOn, CarbonImmutable $endsOn): Series
    {
        $series = Series::query()->firstOrNew(['classroom_id' => $classroom->id, 'slug' => $slug]);
        $series->fill([
            'title' => $title,
            'description' => $description,
            'starts_on' => $startsOn->toDateString(),
            'ends_on' => $endsOn->toDateString(),
        ]);
        $series->classroom_id = $classroom->id;
        $series->save();

        return $series;
    }

    /**
     * @param  list<string>  $questions
     */
    private function pastLesson(Series $series, User $teacher, int $weeksFromNextSunday, string $title, string $reference, string $summary, array $questions): Lesson
    {
        $lesson = new Lesson([
            'title' => $title,
            'summary' => $summary,
            'scheduled_for' => $this->sunday($weeksFromNextSunday)->toDateString(),
            'bible_reference' => $reference,
            'content' => "## Introdução\n\n{$summary}\n\n## Estudo\n\nLeia {$reference} e observe o que o texto revela sobre Jesus, sobre nós e sobre como devemos responder.\n\n## Aplicação\n\nConverse com alguém da classe sobre o que você aprendeu nesta semana.",
            'visibility' => LessonVisibility::Public,
        ]);

        $this->saveLesson($lesson, $series, $teacher, null, LessonStatus::Completed);

        foreach ($questions as $question) {
            $lesson->questions()->create(['body' => $question]);
        }

        $lesson->readings()->create(['weekday' => Weekday::Thursday, 'reference' => $reference, 'notes' => 'Texto base da lição.']);

        return $lesson;
    }

    private function saveLesson(Lesson $lesson, Series $series, User $teacher, ?string $slug, LessonStatus $status): void
    {
        $slug ??= Str::slug($lesson->title);

        $existing = Lesson::withTrashed()->where('slug', $slug)->first();
        if ($existing) {
            $existing->forceDelete();
        }

        $lesson->forceFill([
            'classroom_id' => $series->classroom_id,
            'series_id' => $series->id,
            'slug' => $slug,
            'status' => $status,
            'created_by' => $teacher->id,
            'published_at' => $status === LessonStatus::Draft ? null : now()->subDays(6),
            'completed_at' => $status === LessonStatus::Completed ? now()->subDay() : null,
        ])->save();

        $lesson->authors()->sync([$teacher->id]);
    }

    /**
     * @param  list<array{0: Weekday, 1: string, 2: string}>  $readings
     */
    private function readings(Lesson $lesson, array $readings): void
    {
        foreach ($readings as [$weekday, $reference, $notes]) {
            $lesson->readings()->create(['weekday' => $weekday, 'reference' => $reference, 'notes' => $notes]);
        }
    }

    /**
     * @param  list<string>  $lines
     */
    private function fileMaterial(Lesson $lesson, MaterialType $type, string $title, string $description, string $filename, array $lines, bool $primary = false): void
    {
        $disk = (string) config('ebd.materials.disk');
        $path = config('ebd.materials.directory')."/{$lesson->id}/seed-{$filename}";
        $pdf = self::samplePdf($lines);

        Storage::disk($disk)->put($path, $pdf);

        $material = $lesson->materials()->make([
            'type' => $type,
            'title' => $title,
            'description' => $description,
            'is_primary' => $primary,
        ]);
        $material->forceFill([
            'disk' => $disk,
            'path' => $path,
            'original_name' => $filename,
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen($pdf),
        ])->save();
    }

    private function sunday(int $weeksFromNextSunday): CarbonImmutable
    {
        return $this->nextSunday->addWeeks($weeksFromNextSunday);
    }

    /**
     * Gera um PDF mínimo e válido (uma página, Helvetica) para testes locais.
     *
     * @param  list<string>  $lines
     */
    public static function samplePdf(array $lines): string
    {
        $text = "BT /F1 20 Tf 60 760 Td 26 TL\n";
        foreach ($lines as $line) {
            $safe = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], iconv('UTF-8', 'ASCII//TRANSLIT', $line) ?: $line);
            $text .= "({$safe}) Tj T*\n";
        }
        $text .= 'ET';

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
            '<< /Length '.strlen($text)." >>\nstream\n{$text}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n{$object}\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= 'xref'."\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= 'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";

        return $pdf;
    }
}
