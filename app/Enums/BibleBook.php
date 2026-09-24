<?php

namespace App\Enums;

use Illuminate\Support\Str;

/**
 * Os 66 livros da Bíblia na ordem canônica protestante (a mesma da NAA e dos
 * JSON de bíblia mais comuns). O valor é a posição do livro (1 = Gênesis).
 */
enum BibleBook: int
{
    case Genesis = 1;
    case Exodus = 2;
    case Leviticus = 3;
    case Numbers = 4;
    case Deuteronomy = 5;
    case Joshua = 6;
    case Judges = 7;
    case Ruth = 8;
    case FirstSamuel = 9;
    case SecondSamuel = 10;
    case FirstKings = 11;
    case SecondKings = 12;
    case FirstChronicles = 13;
    case SecondChronicles = 14;
    case Ezra = 15;
    case Nehemiah = 16;
    case Esther = 17;
    case Job = 18;
    case Psalms = 19;
    case Proverbs = 20;
    case Ecclesiastes = 21;
    case SongOfSongs = 22;
    case Isaiah = 23;
    case Jeremiah = 24;
    case Lamentations = 25;
    case Ezekiel = 26;
    case Daniel = 27;
    case Hosea = 28;
    case Joel = 29;
    case Amos = 30;
    case Obadiah = 31;
    case Jonah = 32;
    case Micah = 33;
    case Nahum = 34;
    case Habakkuk = 35;
    case Zephaniah = 36;
    case Haggai = 37;
    case Zechariah = 38;
    case Malachi = 39;
    case Matthew = 40;
    case Mark = 41;
    case Luke = 42;
    case John = 43;
    case Acts = 44;
    case Romans = 45;
    case FirstCorinthians = 46;
    case SecondCorinthians = 47;
    case Galatians = 48;
    case Ephesians = 49;
    case Philippians = 50;
    case Colossians = 51;
    case FirstThessalonians = 52;
    case SecondThessalonians = 53;
    case FirstTimothy = 54;
    case SecondTimothy = 55;
    case Titus = 56;
    case Philemon = 57;
    case Hebrews = 58;
    case James = 59;
    case FirstPeter = 60;
    case SecondPeter = 61;
    case FirstJohn = 62;
    case SecondJohn = 63;
    case ThirdJohn = 64;
    case Jude = 65;
    case Revelation = 66;

    /** Nome em português, como aparece nas referências. */
    public function label(): string
    {
        return match ($this) {
            self::Genesis => 'Gênesis',
            self::Exodus => 'Êxodo',
            self::Leviticus => 'Levítico',
            self::Numbers => 'Números',
            self::Deuteronomy => 'Deuteronômio',
            self::Joshua => 'Josué',
            self::Judges => 'Juízes',
            self::Ruth => 'Rute',
            self::FirstSamuel => '1Samuel',
            self::SecondSamuel => '2Samuel',
            self::FirstKings => '1Reis',
            self::SecondKings => '2Reis',
            self::FirstChronicles => '1Crônicas',
            self::SecondChronicles => '2Crônicas',
            self::Ezra => 'Esdras',
            self::Nehemiah => 'Neemias',
            self::Esther => 'Ester',
            self::Job => 'Jó',
            self::Psalms => 'Salmos',
            self::Proverbs => 'Provérbios',
            self::Ecclesiastes => 'Eclesiastes',
            self::SongOfSongs => 'Cântico dos Cânticos',
            self::Isaiah => 'Isaías',
            self::Jeremiah => 'Jeremias',
            self::Lamentations => 'Lamentações',
            self::Ezekiel => 'Ezequiel',
            self::Daniel => 'Daniel',
            self::Hosea => 'Oseias',
            self::Joel => 'Joel',
            self::Amos => 'Amós',
            self::Obadiah => 'Obadias',
            self::Jonah => 'Jonas',
            self::Micah => 'Miqueias',
            self::Nahum => 'Naum',
            self::Habakkuk => 'Habacuque',
            self::Zephaniah => 'Sofonias',
            self::Haggai => 'Ageu',
            self::Zechariah => 'Zacarias',
            self::Malachi => 'Malaquias',
            self::Matthew => 'Mateus',
            self::Mark => 'Marcos',
            self::Luke => 'Lucas',
            self::John => 'João',
            self::Acts => 'Atos',
            self::Romans => 'Romanos',
            self::FirstCorinthians => '1Coríntios',
            self::SecondCorinthians => '2Coríntios',
            self::Galatians => 'Gálatas',
            self::Ephesians => 'Efésios',
            self::Philippians => 'Filipenses',
            self::Colossians => 'Colossenses',
            self::FirstThessalonians => '1Tessalonicenses',
            self::SecondThessalonians => '2Tessalonicenses',
            self::FirstTimothy => '1Timóteo',
            self::SecondTimothy => '2Timóteo',
            self::Titus => 'Tito',
            self::Philemon => 'Filemom',
            self::Hebrews => 'Hebreus',
            self::James => 'Tiago',
            self::FirstPeter => '1Pedro',
            self::SecondPeter => '2Pedro',
            self::FirstJohn => '1João',
            self::SecondJohn => '2João',
            self::ThirdJohn => '3João',
            self::Jude => 'Judas',
            self::Revelation => 'Apocalipse',
        };
    }

    /**
     * Abreviações e grafias aceitas ao interpretar uma referência, além do nome
     * completo. Comparação sem maiúsculas, espaços e pontos (ver `fromName`).
     *
     * @return list<string>
     */
    public function aliases(): array
    {
        return match ($this) {
            self::Genesis => ['Gn', 'Gen', 'Gên', 'Genesis'],
            self::Exodus => ['Êx', 'Ex', 'Êxo', 'Exo', 'Exodo'],
            self::Leviticus => ['Lv', 'Lev', 'Levitico'],
            self::Numbers => ['Nm', 'Núm', 'Num', 'Numeros'],
            self::Deuteronomy => ['Dt', 'Deut', 'Deuteronomio'],
            self::Joshua => ['Js', 'Jos', 'Josue'],
            self::Judges => ['Jz', 'Juí', 'Jui', 'Juiz', 'Juizes'],
            self::Ruth => ['Rt', 'Rute'],
            self::FirstSamuel => ['1Sm', '1Sam', '1 Samuel', 'ISamuel', 'ISm'],
            self::SecondSamuel => ['2Sm', '2Sam', '2 Samuel', 'IISamuel', 'IISm'],
            self::FirstKings => ['1Rs', '1Re', '1 Reis', 'IReis', 'IRs'],
            self::SecondKings => ['2Rs', '2Re', '2 Reis', 'IIReis', 'IIRs'],
            self::FirstChronicles => ['1Cr', '1Cro', '1Crôn', '1Cron', '1 Crônicas', '1Cronicas', 'ICrônicas', 'ICr'],
            self::SecondChronicles => ['2Cr', '2Cro', '2Crôn', '2Cron', '2 Crônicas', '2Cronicas', 'IICrônicas', 'IICr'],
            self::Ezra => ['Ed', 'Esd', 'Esdras'],
            self::Nehemiah => ['Ne', 'Nee', 'Neem'],
            self::Esther => ['Et', 'Est'],
            self::Job => ['Jó', 'Job'],
            self::Psalms => ['Sl', 'Sal', 'Salm', 'Salmo', 'Salmos'],
            self::Proverbs => ['Pv', 'Pr', 'Prov', 'Proverbios'],
            self::Ecclesiastes => ['Ec', 'Ecl', 'Eclesiastes'],
            self::SongOfSongs => ['Ct', 'Cnt', 'Cant', 'Cânticos', 'Canticos', 'Cantares', 'Cântico', 'Cantico dos Canticos'],
            self::Isaiah => ['Is', 'Isa', 'Isaias'],
            self::Jeremiah => ['Jr', 'Jer', 'Jeremias'],
            self::Lamentations => ['Lm', 'Lam', 'Lamentacoes', 'Lamentações de Jeremias', 'Lamentacoes de Jeremias'],
            self::Ezekiel => ['Ez', 'Eze', 'Ezq', 'Ezequiel'],
            self::Daniel => ['Dn', 'Dan'],
            self::Hosea => ['Os', 'Ose', 'Oséias', 'Oseias'],
            self::Joel => ['Jl', 'Joel'],
            self::Amos => ['Am', 'Amo', 'Amos'],
            self::Obadiah => ['Ob', 'Oba', 'Obad'],
            self::Jonah => ['Jn', 'Jon'],
            self::Micah => ['Mq', 'Miq', 'Miquéias', 'Miqueias'],
            self::Nahum => ['Na', 'Naum'],
            self::Habakkuk => ['Hc', 'Hab', 'Habacuque'],
            self::Zephaniah => ['Sf', 'Sof', 'Sofonias'],
            self::Haggai => ['Ag', 'Age', 'Ageu'],
            self::Zechariah => ['Zc', 'Zac', 'Zacarias'],
            self::Malachi => ['Ml', 'Mal', 'Malaquias'],
            self::Matthew => ['Mt', 'Mat', 'Mateus'],
            self::Mark => ['Mc', 'Mr', 'Mar', 'Marcos'],
            self::Luke => ['Lc', 'Luc', 'Lucas'],
            self::John => ['Jo', 'Joa', 'Joao', 'João'],
            self::Acts => ['At', 'Atos', 'Atos dos Apóstolos', 'Atos dos Apostolos'],
            self::Romans => ['Rm', 'Ro', 'Rom', 'Romanos'],
            self::FirstCorinthians => ['1Co', '1Cor', '1 Coríntios', '1Corintios', 'ICoríntios', 'ICo'],
            self::SecondCorinthians => ['2Co', '2Cor', '2 Coríntios', '2Corintios', 'IICoríntios', 'IICo'],
            self::Galatians => ['Gl', 'Ga', 'Gal', 'Gálatas', 'Galatas'],
            self::Ephesians => ['Ef', 'Efe', 'Efes', 'Efésios', 'Efesios'],
            self::Philippians => ['Fp', 'Fl', 'Flp', 'Fil', 'Filip', 'Filipenses'],
            self::Colossians => ['Cl', 'Col', 'Colossenses'],
            self::FirstThessalonians => ['1Ts', '1Tes', '1Tess', '1 Tessalonicenses', '1Tessalonicenses', 'ITs'],
            self::SecondThessalonians => ['2Ts', '2Tes', '2Tess', '2 Tessalonicenses', '2Tessalonicenses', 'IITs'],
            self::FirstTimothy => ['1Tm', '1Ti', '1Tim', '1 Timóteo', '1Timoteo', 'ITm'],
            self::SecondTimothy => ['2Tm', '2Ti', '2Tim', '2 Timóteo', '2Timoteo', 'IITm'],
            self::Titus => ['Tt', 'Tit', 'Tito'],
            self::Philemon => ['Fm', 'Flm', 'Filem', 'Filemom', 'Filemon'],
            self::Hebrews => ['Hb', 'Heb', 'Hebreus'],
            self::James => ['Tg', 'Tia', 'Tiago'],
            self::FirstPeter => ['1Pe', '1Pd', '1Ped', '1 Pedro', 'IPe', 'IPedro'],
            self::SecondPeter => ['2Pe', '2Pd', '2Ped', '2 Pedro', 'IIPe', 'IIPedro'],
            self::FirstJohn => ['1Jo', '1Joao', '1 João', 'IJo', 'IJoão'],
            self::SecondJohn => ['2Jo', '2Joao', '2 João', 'IIJo', 'IIJoão'],
            self::ThirdJohn => ['3Jo', '3Joao', '3 João', 'IIIJo', 'IIIJoão'],
            self::Jude => ['Jd', 'Jud', 'Judas'],
            self::Revelation => ['Ap', 'Apc', 'Apoc', 'Apocalipse', 'Rev'],
        };
    }

    /**
     * Encontra o livro pelo nome ou abreviação escrita numa referência
     * ("Lucas", "Lc", "1 Coríntios", "1Co", "Jó", "Jo.").
     *
     * A comparação ignora maiúsculas, espaços e pontos. Primeiro tenta com os
     * acentos (para "Jó" não virar "Jo" = João) e só depois sem acentos
     * ("Joao", "Genesis").
     */
    public static function fromName(string $name): ?self
    {
        $exact = self::key($name, keepAccents: true);
        $loose = self::key($name, keepAccents: false);

        if ($exact === '') {
            return null;
        }

        $fallback = null;

        foreach (self::cases() as $book) {
            foreach ([$book->label(), ...$book->aliases()] as $candidate) {
                if (self::key($candidate, keepAccents: true) === $exact) {
                    return $book;
                }

                if ($fallback === null && self::key($candidate, keepAccents: false) === $loose) {
                    $fallback = $book;
                }
            }
        }

        return $fallback;
    }

    private static function key(string $name, bool $keepAccents): string
    {
        $name = mb_strtolower(trim($name));

        if (! $keepAccents) {
            $name = Str::ascii($name);
        }

        return (string) preg_replace('/[\s.]+/u', '', $name);
    }
}
