# 11 · Performance e confiabilidade

Auditoria EBD 360 · papel: Performance & Reliability Engineer · data: 2026-10-19 · branch `engineering/ebd-360`

Regras seguidas: só leitura do código. Não rodei `npm run build`: os bundles foram medidos sobre `public/build`, já existente. O banco de desenvolvimento foi lido com `SELECT`/`EXPLAIN`. Os microbenchmarks rodaram em PHP 8.5.4 CLI local, sem opcache. Produção roda PHP 8.4 no FrankenPHP, então os tempos servem de ordem de grandeza. Os scripts ficaram no scratchpad da sessão, fora do repositório.

Classificação: **Confirmado** / **Risco** / **Hipótese** / **Preferência**, com severidade de P0 a P3.

---

## 1. Medições

### 1.1 Bundles (`public/build`, gzip -9)

Comando: script Python sobre `public/build/manifest.json`. Ele soma bytes brutos e `gzip.compress(..., 9)` do fechamento de `imports` estáticos de cada entrada.

| Item                                                                                                 | Bruto      | gzip       |
| ---------------------------------------------------------------------------------------------------- | ---------- | ---------- |
| Todos os assets (144 arquivos)                                                                       | 1.840 KB   | 973 KB     |
| **JS inicial** (`app.tsx` + 10 imports estáticos)                                                    | **727 KB** | **211 KB** |
| `wayfinder-*.js`, chunk de vendor: React, react-dom, Inertia, axios, lucide e o runtime do wayfinder | 373 KB     | 116 KB     |
| `app-*.js` (layouts, providers, flowbite, popper, radix/sonner)                                      | 306 KB     | 78 KB      |
| `dialog-*.js` (no carregamento inicial)                                                              | 31 KB      | 10 KB      |
| CSS `app-*.css`                                                                                      | 127 KB     | 21 KB      |
| Página `lessons/show` (só o que é dela)                                                              | 65 KB      | 28 KB      |
| Página `lessons/sunday`                                                                              | 40 KB      | 18 KB      |
| Página `home`                                                                                        | 54 KB      | 22 KB      |
| Página `admin/lessons/edit`                                                                          | 91 KB      | 39 KB      |
| Página `admin/classrooms/show`                                                                       | 38 KB      | 16 KB      |

As páginas já são carregadas sob demanda: 34 `dynamicImports` em `app.tsx`. As fontes (Inter e Literata, 25 a 85 KB cada) usam `unicode-range`, então cirílico e latin-ext só são baixados se aparecerem no texto.

### 1.2 Render do conteúdo da lição (`Markdown::toHtml`)

Script: `md_bench*.php` no scratchpad (carrega `vendor/autoload.php` e roda `App\Support\Markdown::toHtml` em laço).

| Caso                                                     | Tempo médio                                                                                                                   |
| -------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------- |
| Lição sintética de 17 KB com 14 referências bíblicas     | **32 ms**                                                                                                                     |
| 14 KB de markdown denso, com 456 referências             | 622 ms (CommonMark 36 ms + `BibleLinks::link` 613 ms)                                                                         |
| `BibleLinks::link` em 14 KB **sem** referências          | 0,09 ms                                                                                                                       |
| `BibleBook::fromName()` (por chamada)                    | **0,61 ms**                                                                                                                   |
| `Reference::parse()` (por referência)                    | 0,62 ms, quase tudo dentro de `fromName`                                                                                      |
| Mesma busca com mapa memoizado (protótipo no scratchpad) | **0,0012 ms**, com 8 ms para montar o mapa uma vez por processo e resultado idêntico para Hb, Rm, Jo, Gl, Ef, 1 Co, Jó e Joao |

### 1.3 Banco (EXPLAIN no dev)

- Trecho bíblico (`Bible::passage`, Jo 3.16-18): `Bitmap Index Scan on bible_verses_book_chapter_verse_unique`, **0,6 ms**.
- `whereDate('held_on', ...)` em coluna `date` usa `class_meetings_classroom_id_held_on_unique` (não estraga o índice).
- Busca com título da série: o GIN não é usado (ver `06-database-audit.md`, D-6).

Não medi: o tamanho real das props Inertia por página. Não subi o app, porque `ClassMeetingController::index` escreve no banco num GET (`FillSundays`, `ClassMeetingController.php:46`) e o banco de testes estava em uso. O dev também não tem lição com tamanho de produção.

---

## 2. Achados

### P-1 · Confirmado · P2 · confiança alta: `BibleBook::fromName` é O(nomes) com `Str::ascii` a cada chamada

`app/Enums/BibleBook.php:239-263` percorre os 66 livros e todos os aliases (288 nomes) e recalcula `key()`, com `mb_strtolower`, `Str::ascii` e `preg_replace`, **para cada candidato em cada chamada**. Ele é chamado duas vezes por referência no texto: em `BibleLinks::resolve` (`BibleLinks.php:96-125`) e de novo em `Reference::parse`. Também é chamado em cada `Bible::passage`. Medido: 0,61 ms por chamada, cerca de 1,3 ms por referência linkada. Isso aparece em:

- cada visualização da lição e do Modo Domingo (`LessonResource.php:86`, `content_html`);
- cada lição de **lista**: `LessonResource.php:68` calcula `key_verse_passage` (parse + 1 query) mesmo na biblioteca (12 por página) e na lista da gestão (`Admin/LessonController.php:69`, 50 por página), e o front só usa esse campo no hero e no cabeçalho da lição (`lesson-hero.tsx`, `revista-header.tsx`);
- a narração do áudio (`StudyNarration` usa `BibleLinks::replace`).

**Correção, baixo risco:** mapa estático `key → BibleBook` montado uma vez (exato e sem acento, mantendo a ordem de prioridade atual). **Ganho medido:** ~500× por busca, de 0,61 ms para 0,0012 ms. Na lição sintética de 14 referências o ganho esperado é de ~18 ms do total de 32 ms. Para o caso de lista, ver P-3.

### P-2 · Confirmado · P2 · confiança alta: no Modo Domingo, cada toque na chamada recarrega a lição inteira

`resources/js/pages/lessons/sunday.tsx:312-319` usa `<AttendanceSheet>` **sem** `reloadOnly`. Em `attendance-sheet.tsx:53-63` isso vira `only: undefined`. O `PUT` responde com `back()` (`AttendanceController.php:32`) e o Inertia refaz o GET completo de `lessons.sunday`: `present()` (`LessonController.php:151-169`) recarrega 7 relações, roda `Markdown::toHtml` (P-1, cerca de 32 ms numa lição de 17 KB), consulta de 2 a 3 trechos bíblicos e reenvia todo o HTML do estudo, os blocos e os materiais. Na tela de encontro da gestão o mesmo componente já usa `reloadOnly={['summary','meeting']}` (`meetings/show.tsx:235`).
**Correção, baixo risco:** `reloadOnly={['conduct']}` no Modo Domingo. O ganho esperado não foi medido em bytes. Em CPU, cada salvamento deixa de fazer pelo menos o render do markdown (P-1) e as consultas de lição e Bíblia.

### P-3 · Confirmado · P3 · confiança alta: `key_verse_passage` calculado em listas que não o exibem

Ver P-1. Na lista da gestão (50 lições) são até 50 `Reference::parse` (cerca de 31 ms medidos, 0,61 ms × 50) mais até 50 queries em `bible_verses` por página. Na biblioteca são até 12. **Correção:** só incluir o campo com `withContent()` ou quando a página precisar (home e lição). Não precisa limpar dados.

### P-4 · Risco · P2 · confiança média: o "Tirar dúvida" é síncrono, com timeout de 60 s e `max_execution_time = 30`

`AnswerStudyQuestion.php:83-88` chama a OpenAI dentro do request com `EBD_HELPER_TIMEOUT=60` (`config/ebd.php:124`). A imagem de produção define `max_execution_time = 30` (`docker/frankenphp/php.ini:19`). **Hipótese a verificar:** no PHP ZTS sobre Linux, que é o caso do FrankenPHP, o timer de `max_execution_time` mede tempo de relógio (timer `CLOCK_BOOTTIME`), não CPU. Se for assim, uma resposta que passe de 30 s mata o request com fatal error e não com a mensagem amigável. Ao menos a pergunta não é contada. Teste no homolog: subir `EBD_HELPER_TIMEOUT` e simular uma resposta lenta, ou rodar `php -r 'set_time_limit(2); sleep(5); echo "ok";'` sob o FrankenPHP. **Correção:** `set_time_limit((int) config('ebd.helper.timeout') + 10)` no handle, ou alinhar o timeout a menos de 30 s. A geração de áudio já faz `set_time_limit(0)` (`GenerateLessonAudio.php:32`).

### P-5 · Risco · P2 · confiança média: trabalho pesado ocupa threads do servidor web

Produção não tem fila (`QUEUE_CONNECTION=sync`, segundo a memória do projeto; o `Dockerfile` não instala cliente Redis). Rodam dentro do processo web:

- **Áudio:** `RequestLessonAudio.php:60-66` usa `app()->terminating()`. A resposta sai, mas a thread do FrankenPHP fica presa por minutos (ListeningScript + TTS por trecho, timeout de 180 s cada, `config/ebd.php:109`).
- **Push na publicação:** `ChangeLessonStatus.php:44-50` dispara o evento de forma síncrona. `WebPushSender.php:34-71` faz flush paralelo, mas quem publicou espera todas as entregas.
- **E-mail de redefinição de senha:** `ResetPasswordNotification` é `ShouldQueue`, mas com `sync` o envio SMTP acontece no request.
- **IA:** até 60 s por pergunta (P-4).

Com poucas threads (o padrão do FrankenPHP é 2× CPU), uma geração de áudio junto com algumas dúvidas pode deixar a página lenta para todos no domingo de manhã. **Recomendação:** um worker `queue:work` com fila `database` (já existe a tabela `jobs`), no mesmo esquema do serviço `scheduler`, e mover áudio, push e IA para jobs. É uma mudança média, não baixa. Não medi o impacto.

### P-6 · Confirmado · P3 · confiança alta: o cache do service worker cresce a cada deploy

`public/sw.js:66-81` guarda todo `/build/*` (cache-first) em `ebd-v4-static`. O `activate` (`:30-41`) só apaga caches de **outra** `VERSION` (`:11`), que é fixa à mão. Os nomes dos arquivos mudam a cada build, então os chunks antigos nunca saem: cada deploy acrescenta pelo menos o JS inicial (727 KB brutos medidos) ao aparelho de cada usuário. **Correção, baixo risco:** no `activate` ou após `fetch`, apagar entradas `/build/` que não estejam em uma lista gerada no build, ou colocar o hash do manifest na `VERSION` (por exemplo, `sw.js` servido por rota com a versão do Vite). O ponto positivo é que o cache-first mantém disponíveis os chunks antigos para abas abertas, o que complementa o `vite:preloadError` (`app.tsx:63-78`).

### P-7 · Risco · P3: não há estratégia offline para o conteúdo

Navegação sem rede cai em `/offline.html` (`sw.js:58-63`). Pedidos Inertia (XHR) não são cacheados. Quem já abriu a lição continua lendo o que está na memória, mas a chamada que falha fica em "erro" (`attendance-sheet.tsx:61`) **sem nova tentativa automática**. Só volta a salvar no próximo toque. Isso é uma escolha consciente para não guardar HTML autenticado (`sw.js:5-7`), mas o Modo Domingo é justamente o uso em Wi-Fi ruim. **Recomendação:** tentar de novo com backoff quando o salvamento falhar ou ao receber o evento `online`. É só front e de baixo risco, porque a lista enviada é sempre completa.

### P-8 · Preferência · P3: `import 'flowbite'` global

`app.tsx:4` importa o pacote inteiro, que registra todos os componentes por `data-*` e inclui o popper. O único uso é `Drawer`, em `components/ui/bottom-sheet.tsx:1`. Nenhum `data-drawer`, `data-dropdown` ou `data-tooltip-target` aparece no TSX. O teto do ganho é o `flowbite.min.js` inteiro, 29 KB gzip (medido em `node_modules/flowbite/dist`), mas o ganho real não foi medido porque não rodei o build. Como o pacote não declara `sideEffects`, o import em `bottom-sheet` pode continuar puxando o índice. Para ver o ganho é preciso rodar o build antes e depois.

### P-9 · Preferência · P3: trabalho repetido no Resumo da classe

`ClassroomOverviewController.php:27` passa `upcoming` como closure, mas no carregamento completo ela também é avaliada. Ela chama `week()` de novo (`ClassroomOverviewQuery.php:138`), e `for()` já tinha chamado (`:43`). `week()` roda `CurrentLessonQuery::for` uma ou duas vezes, então o Resumo faz de 2 a 4 vezes a mesma consulta da lição atual (cerca de 4 queries cada). Além disso, `books->for($classroom)` (`:45`) monta o livro de chamada de **todo o histórico** (`AttendanceBookQuery.php:23`, `Period::allTime()`), que cresce cerca de 52 encontros por ano. Hoje isso é barato. **Correção:** memoizar `week()` por request.

### P-10 · Preferência · P3: chamada pelo MCP com muitos alunos de uma vez

`RecordAttendance.php:84` roda `AwardBadges::handle` por aluno recém-marcado (cerca de 8 queries cada). Pela tela é um aluno por toque. Pelo MCP (`record_attendance`) com 30 alunos são cerca de 240 queries num request. Aceitável, mas vale agrupar se a escala crescer.

### P-11 · Preferência · P3: infraestrutura web

- `docker/frankenphp/Caddyfile:12` usa `level DEBUG` em produção, o que gera muito log JSON por request. Sugestão: `INFO`.
- Não há `Cache-Control: immutable` para `/build/*`, que tem hash no nome. Para quem não usa o PWA, o navegador fica na heurística do Last-Modified. Sugestão: `header /build/* Cache-Control "public, max-age=31536000, immutable"` e `no-cache` para `/sw.js`. A compressão `zstd br gzip` já está ligada.
- `start-container.sh` roda `migrate --force` e `optimize` a cada boot. Está correto, mas aumenta o tempo de subida.

### P-12 · O que está bem

Props compartilhadas enxutas (`HandleInertiaRequests.php:39-75`). Paginação na biblioteca (12), na lista de lições (50) e nas próximas lições (4, carregadas à parte com `only: ['upcoming']`). Livro de chamada em 4 queries (`AttendanceBookQuery.php`). Push enviado em paralelo e inscrições expiradas apagadas na hora (`WebPushSender.php:55-73`). Claim atômico do áudio. `vite:preloadError` com reload único. Conteúdo do professor filtrado na própria consulta (`LessonController.php:158-169`).

## 3. Recomendações por ordem de custo/benefício

| #    | Mudança                                    | Risco                                        | Ganho                                                                                               |
| ---- | ------------------------------------------ | -------------------------------------------- | --------------------------------------------------------------------------------------------------- |
| P-1  | memoizar `BibleBook::fromName`             | baixo (teste de equivalência com os aliases) | **medido:** 0,61 → 0,0012 ms por busca; cerca de 18 de 32 ms numa lição de 17 KB com 14 referências |
| P-2  | `reloadOnly={['conduct']}` no Modo Domingo | baixo                                        | não medido em bytes; elimina o render do estudo a cada salvamento                                   |
| P-3  | `key_verse_passage` só com conteúdo        | baixo (conferir o home)                      | **medido:** cerca de 31 ms de CPU e até 50 queries por página da lista da gestão                    |
| P-4  | `set_time_limit` no "Tirar dúvida"         | baixo                                        | confiabilidade (evita fatal), se a hipótese se confirmar                                            |
| P-7  | nova tentativa automática da chamada       | baixo                                        | confiabilidade em Wi-Fi ruim                                                                        |
| P-6  | limpar `/build` antigo do cache do SW      | baixo                                        | armazenamento: pelo menos 727 KB por deploy por aparelho                                            |
| P-11 | `Cache-Control immutable` + log `INFO`     | baixo                                        | não medido                                                                                          |
| P-8  | remover `import 'flowbite'` global         | baixo                                        | ≤ 29 KB gzip (teto; medir com build)                                                                |
| P-5  | worker de fila (áudio, push, IA)           | médio (novo serviço no Railway)              | não medido; isola o servidor web de chamadas longas                                                 |
