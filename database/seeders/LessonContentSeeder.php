<?php

namespace Database\Seeders;

use App\Actions\Lessons\SyncLessonSearchText;
use App\Enums\ContentAudience;
use App\Enums\LessonBlockKind;
use App\Enums\LessonStatus;
use App\Enums\LessonVisibility;
use App\Enums\MaterialType;
use App\Enums\MeetingStatus;
use App\Enums\Weekday;
use App\Models\ClassMeeting;
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
            'Nas bodas de Caná, Jesus manifesta sua glória e os discípulos creem nele. O primeiro sinal aponta para a alegria da nova aliança.');

        $this->pastLesson($series, $teacher, -3, 'A Cura do Paralítico de Cafarnaum', 'Marcos 2:1-12',
            'Quatro amigos abrem o telhado para levar alguém até Jesus. Antes de curar o corpo, Jesus perdoa pecados e revela sua autoridade.');

        $this->pastLesson($series, $teacher, -2, 'Jesus Acalma a Tempestade', 'Marcos 4:35-41',
            'No meio da tempestade os discípulos perguntam se Jesus se importa. A resposta vem com autoridade sobre o vento e o mar.');

        $this->pastLesson($series, $teacher, -1, 'A Multiplicação dos Pães', 'João 6:1-14',
            'Cinco pães e dois peixes nas mãos de Jesus alimentam uma multidão. O pouco entregue a Ele se torna suficiente.');

        $this->santidadeDeDeus($series, $teacher);
        $this->eNecessario($series, $teacher);

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
            'visibility' => LessonVisibility::Public,
        ]);

        $this->saveLesson($lesson, $series, $teacher, 'a-santidade-de-deus', LessonStatus::Published);

        $lesson->blocks()->create([
            'kind' => LessonBlockKind::TeacherNote,
            'audience' => ContentAudience::Teacher,
            'title' => 'Notas do professor',
            'body' => <<<'MD'
- **Abertura (5 min):** perguntar quem já passou uma "noite inteira sem pescar nada" em alguma área da vida.
- **Leitura (5 min):** pedir a um aluno para ler Lucas 5:1–11 em voz alta.
- **Discussão (25 min):** dar mais tempo à reação de Pedro (v. 8) e ao chamado (v. 10).
- Ligar com Isaías 6 (leitura de segunda-feira) — muitos devem ter lido.
- **Encerramento:** oração pedindo reverência e disposição para obedecer.
- Avisar que na próxima semana veremos a cura do leproso (Lucas 5:12-16).
MD,
        ]);

        $this->readings($lesson, [
            [Weekday::Monday, 'Isaías 6:1-8', 'A visão de Isaías: "Santo, santo, santo". Compare a reação de Isaías com a de Pedro.'],
            [Weekday::Tuesday, 'Êxodo 3:1-6', 'Moisés diante da sarça: "tira as sandálias, o lugar é santo".'],
            [Weekday::Wednesday, 'Salmo 99', 'O Senhor reina — Ele é santo. Leia em voz alta, se puder.'],
            [Weekday::Thursday, 'Lucas 5:1-11', 'Texto base. Leia duas vezes, com calma, e anote o que mais chamou sua atenção.'],
            [Weekday::Friday, '1 Pedro 1:13-16', 'Anos depois, o próprio Pedro escreve: "sede santos".'],
            [Weekday::Saturday, 'Hebreus 12:14, 28-29', 'Prepare o coração para o domingo.'],
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
            '3. Anote o que Deus falou com você',
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
            'Depois de alimentar a multidão, Jesus se apresenta como o pão que desce do céu e sacia de verdade.');

        $this->pastLesson($joao, $teacher, -53, 'Eu Sou a Luz do Mundo', 'João 8:12-20',
            'Quem segue a Jesus não anda em trevas. A luz revela, orienta e aquece.');

        $filipenses = $this->series($classroom, 'Filipenses: Alegria em Cristo', 'filipenses-alegria-em-cristo',
            'Uma carta escrita da prisão que fala de alegria do começo ao fim.',
            $this->sunday(-2), $this->sunday(3));

        $this->pastLesson($filipenses, $teacher, -2, 'Alegria em Toda Circunstância', 'Filipenses 1:1-11',
            'Paulo ora com alegria pelos filipenses, confiante de que Deus completará a boa obra que começou.');

        $restricted = $this->pastLesson($filipenses, $teacher, -1, 'Viver é Cristo', 'Filipenses 1:12-30',
            'Mesmo preso, Paulo vê o evangelho avançar. Para ele, viver é Cristo e morrer é lucro.');
        $restricted->forceFill(['visibility' => LessonVisibility::Members])->save();

        $next = new Lesson([
            'title' => 'O Exemplo de Cristo',
            'summary' => 'Humildade não é pensar menos de si, mas pensar mais nos outros. Cristo se esvaziou e foi exaltado.',
            'scheduled_for' => $this->nextSunday->toDateString(),
            'bible_reference' => 'Filipenses 2:1-11',
            'content' => "## Introdução\n\nO hino de Filipenses 2 é um dos textos mais antigos sobre quem é Jesus.\n\n## O mesmo sentimento\n\nPaulo pede unidade, humildade e cuidado mútuo.\n\n## O caminho da cruz\n\nCristo, sendo Deus, não se apegou à sua posição: esvaziou-se, fez-se servo e obedeceu até a morte.",
            'visibility' => LessonVisibility::Public,
        ]);
        $this->saveLesson($next, $filipenses, $teacher, 'o-exemplo-de-cristo', LessonStatus::Published);
        $next->blocks()->create([
            'kind' => LessonBlockKind::TeacherNote,
            'audience' => ContentAudience::Teacher,
            'body' => '- Dividir a turma em duplas para ler os versos 5 a 8.',
        ]);

        $this->readings($next, [
            [Weekday::Monday, 'Filipenses 2:1-4', 'O mesmo sentimento e o mesmo amor.'],
            [Weekday::Wednesday, 'Filipenses 2:5-11', 'Leia devagar: é um hino.'],
            [Weekday::Friday, 'João 13:1-17', 'Jesus lava os pés dos discípulos.'],
        ]);
        $next->materials()->create([
            'type' => MaterialType::Reference,
            'title' => 'Comentário de Filipenses — John Stott',
            'description' => 'Série "A Bíblia Fala Hoje".',
        ]);
    }

    /**
     * Lição no formato completo da revista, com blocos de aprofundamento,
     * e curiosidades liberadas ao longo da semana.
     */
    private function eNecessario(Series $series, User $teacher): void
    {
        $lesson = new Lesson([
            'number' => 11,
            'title' => 'É Necessário',
            'summary' => 'Na cura do cego de nascença, Jesus desmonta a lógica do "quem pecou?" e se revela como a luz do mundo.',
            'scheduled_for' => $this->sunday(2)->toDateString(),
            'bible_reference' => 'Jo 9.1-41',
            'key_verse' => '"É necessário que façamos as obras daquele que me enviou enquanto é dia; a noite vem, quando ninguém pode trabalhar. Enquanto estou no mundo, sou a luz do mundo." (Jo 9.4-5)',
            'goal' => 'Analisar de que maneiras Jesus Cristo demonstrou ser a luz do mundo no episódio da cura do cego de nascença.',
            'content' => <<<'MD'
Quando alguém se vê subitamente acometido por uma doença, não é raro pensar: *por que o Senhor permitiu isso? Será castigo?* Foi assim que os discípulos perguntaram: *"Mestre, quem pecou para que este homem nascesse cego?"* (Jo 9.2).

## I. "Quem pecou?"

A pergunta refletia a cosmovisão da época. Jesus quebra o paradigma: *"Nem ele pecou, nem seus pais"* (Jo 9.3). A cegueira não era castigo, mas palco para a obra de Deus.

## II. "É necessário"

### 1. É necessário ser curado da cegueira espiritual

Todos nascemos com o entendimento obscurecido pelo pecado (1Co 2.14).

### 2. É necessário que façamos a obra de Deus

Jesus inclui os discípulos: *"é necessário que **façamos**"*. E há urgência: *"enquanto é dia"*.

## III. "Eu sou a luz do mundo"

O ex-cego testemunha sem ter visto Jesus, e o reencontra para crer e adorar (v.38). Os fariseus, com olhos perfeitos, permanecem cegos (v.40-41).

## Conclusão

Aquele que é a luz da nossa vida nos comanda a ser luz do mundo (Mt 5.16).
MD,
            'visibility' => LessonVisibility::Public,
        ]);

        $this->saveLesson($lesson, $series, $teacher, 'e-necessario', LessonStatus::Published);

        $this->readings($lesson, [
            [Weekday::Monday, 'Jo 1.1-14', 'A luz verdadeira que ilumina a todo homem.'],
            [Weekday::Tuesday, '1Co 2.4-16', 'O homem natural não compreende as coisas do Espírito.'],
            [Weekday::Wednesday, 'Rm 12.1-2', 'Transformados pela renovação da mente.'],
            [Weekday::Thursday, 'Jo 3.16-21', 'A luz veio ao mundo — e o juízo que ela provoca.'],
            [Weekday::Friday, 'Mt 5.14-16', 'Vós sois a luz do mundo.'],
            [Weekday::Saturday, 'Ef 5.1-14', 'Andai como filhos da luz.'],
            [Weekday::Sunday, '1Ts 5.1-11', 'Filhos do dia.'],
        ]);

        $blocks = [
            [LessonBlockKind::Roteiro, 'Fio da aula', "1. Abrir com a pergunta: *\"o que eu fiz para merecer isso?\"*\n2. Contexto: Festa dos Tabernáculos (luz e água).\n3. Ler o capítulo como drama em sete cenas.\n4. A escada de fé do ex-cego (v.11, 17, 33, 38).\n5. Fechar com \"Uma coisa sei: eu era cego e agora vejo\" (v.25).", null],
            [LessonBlockKind::ExtraTime, 'Saliva como "remédio" no mundo antigo', 'Tácito registra que Vespasiano teria "curado" um cego em Alexandria com saliva. Jesus usa um gesto conhecido — mas o resultado não tinha paralelo.', null],
            [LessonBlockKind::AccuracyNote, 'Globos oculares?', 'A revista menciona que o homem "possivelmente nasceu sem os globos oculares". Trate como **especulação homilética**, não como dado do texto: João diz apenas que ele era cego de nascença (v.1).', null],
            [LessonBlockKind::Context, 'O pano de fundo: a Festa dos Tabernáculos', "João 9 fecha o bloco iniciado em João 7, durante Sucot. Dois rituais iluminam o capítulo:\n\n- **A cerimônia da iluminação:** quatro candelabros gigantes no Pátio das Mulheres. É nesse cenário que Jesus diz \"Eu sou a luz do mundo\" (Jo 8.12; 9.5).\n- **A libação da água:** todo dia um sacerdote buscava água justamente na piscina de Siloé.", null],
            [LessonBlockKind::Context, 'A expulsão da sinagoga', 'O medo dos pais (v.22) gira em torno de *aposynagōgos*, palavra que só aparece em João. Ser expulso significava morte social e econômica — o que torna a coragem do ex-cego ainda maior.', null],
            [LessonBlockKind::Theology, 'A escada de fé do ex-cego', "1. \"Um homem chamado Jesus\" (v.11)\n2. \"É um profeta\" (v.17)\n3. \"Se este homem não fosse de Deus, nada poderia fazer\" (v.33)\n4. \"Eu creio, Senhor!\" — e o adorou (v.38)", null],
            [LessonBlockKind::Application, 'O testemunho do "eu não sei, mas sei"', 'Pressionado por especialistas, o ex-cego não venceu o debate: disse apenas *"Uma coisa sei: eu era cego e agora vejo"* (v.25). Ninguém refuta um testemunho vivido.', null],
            [LessonBlockKind::Curiosity, 'Siloé significa "Enviado"', 'João faz questão de traduzir (v.7). Jesus é chamado de "Enviado" do Pai mais de 40 vezes no evangelho: ao se lavar em Siloé, o cego mergulhava, simbolicamente, no próprio Cristo.', Weekday::Monday],
            [LessonBlockKind::Curiosity, 'A piscina de Siloé foi encontrada em 2004', 'Operários em uma obra de esgoto na Cidade de Davi encontraram os degraus da piscina do Segundo Templo — a mesma de João 9. Dá para visitar.', Weekday::Wednesday],
            [LessonBlockKind::Curiosity, 'Nenhum cego curado no Antigo Testamento', 'Nem Moisés, nem Elias, nem Eliseu. Abrir olhos de cegos era a assinatura do Messias (Is 35.5; 42.7) — e o ex-cego percebe isso no v.32.', Weekday::Friday],
            [LessonBlockKind::Concept, 'As 39 categorias de trabalho proibido (melachot)', 'A tradição oral listava 39 trabalhos proibidos no sábado (Mishná, Shabat 7.2). Ao fazer lama, Jesus "amassou" — violando a interpretação rabínica, não o sábado bíblico (Mc 2.27-28).', null],
            [LessonBlockKind::Concept, 'As sete obras-sinais de João', "1. Água em vinho (Jo 2)\n2. Filho do oficial (Jo 4)\n3. Paralítico de Betesda (Jo 5)\n4. Multiplicação dos pães (Jo 6)\n5. Jesus anda sobre o mar (Jo 6)\n6. **Cego de nascença (Jo 9)**\n7. Lázaro (Jo 11)", null],
            [LessonBlockKind::Concept, 'As sete declarações "Eu Sou"', 'Pão da vida, **luz do mundo**, porta, bom pastor, ressurreição e vida, caminho-verdade-vida, videira verdadeira. Todas ecoam o "EU SOU" de Êxodo 3.14.', null],
        ];

        foreach ($blocks as [$kind, $title, $body, $drip]) {
            $lesson->blocks()->create([
                'kind' => $kind,
                'audience' => $kind->defaultAudience(),
                'title' => $title,
                'body' => $body,
                'drip_weekday' => $drip,
            ]);
        }

        app(SyncLessonSearchText::class)->handle($lesson);
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

    private function pastLesson(Series $series, User $teacher, int $weeksFromNextSunday, string $title, string $reference, string $summary): Lesson
    {
        $lesson = new Lesson([
            'title' => $title,
            'summary' => $summary,
            'scheduled_for' => $this->sunday($weeksFromNextSunday)->toDateString(),
            'bible_reference' => $reference,
            'content' => "## Introdução\n\n{$summary}\n\n## Estudo\n\nLeia {$reference} e observe o que o texto revela sobre Jesus, sobre nós e sobre como devemos responder.\n\n## Aplicação\n\nConverse com alguém da classe sobre o que você aprendeu nesta semana.",
            'visibility' => LessonVisibility::Public,
        ]);

        $this->saveLesson($lesson, $series, $teacher, null, LessonStatus::Published);

        $lesson->readings()->create(['weekday' => Weekday::Thursday, 'reference' => $reference, 'notes' => 'Texto base da lição.']);

        return $lesson;
    }

    private function saveLesson(Lesson $lesson, Series $series, User $teacher, ?string $slug, LessonStatus $status): void
    {
        $slug ??= Str::slug($lesson->title);

        $existing = Lesson::withTrashed()->where('slug', $slug)->first();
        if ($existing) {
            ClassMeeting::query()->where('lesson_id', $existing->id)->delete();
            $existing->forceDelete();
        }

        $lesson->forceFill([
            'classroom_id' => $series->classroom_id,
            'series_id' => $series->id,
            'slug' => $slug,
            'status' => $status,
            'created_by' => $teacher->id,
            'published_at' => $status === LessonStatus::Draft ? null : now()->subDays(6),
        ])->save();

        $lesson->authors()->sync([$teacher->id]);

        // A data da lição vira um encontro na agenda da classe (realizado se já passou).
        if ($lesson->scheduled_for !== null) {
            ClassMeeting::query()->updateOrCreate(
                ['classroom_id' => $lesson->classroom_id, 'held_on' => $lesson->scheduled_for->toDateString()],
                [
                    'lesson_id' => $lesson->id,
                    'status' => $lesson->scheduled_for->lt(ChurchCalendar::today()) ? MeetingStatus::Held : MeetingStatus::Planned,
                ],
            );
        }
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
