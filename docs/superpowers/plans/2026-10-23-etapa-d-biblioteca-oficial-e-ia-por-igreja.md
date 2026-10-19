# Etapa D: biblioteca oficial, cópia com versão e IA por igreja — plano

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** o Simon controla, por igreja, a IA (dúvidas e áudio) e o texto bíblico, e vê só números de cada igreja; e passa a ter uma biblioteca oficial de estudos que qualquer igreja copia para uma turma, com aviso e comparação quando sai uma versão nova.

**Architecture:** duas entregas, cada uma num PR próprio, mergeado com o CI verde, como na etapa C.

- **D1 (configurações por igreja + painel do dono):**
    - colunas de configuração em `churches`;
    - um serviço `ChurchFeatures` que todo ponto de IA e de texto bíblico consulta;
    - a tela `/dono`, que mostra só números.
- **D2 (biblioteca oficial):**
    - a biblioteca é uma **organização especial** (`churches.is_official`), com uma turma "Estudos oficiais" de que o Simon é admin. Assim ele continua usando as telas de lição, blocos, leituras e materiais e as ferramentas do MCP sem nada novo para escrever o estudo;
    - "publicar versão" tira um **retrato imutável** (JSON) da série;
    - a cópia sai sempre do último retrato e guarda de onde veio e de qual versão;
    - a comparação é entre o retrato da versão copiada e o mais recente.

**Tech Stack:** Laravel 13 / PHP 8.4, Inertia 3 + React 19 + TS, Postgres, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-10-19-plataforma-multi-igreja-design.md`, com as decisões que mudam o spec em `docs/didache/00-diagnostico.md` §7:

- acesso livre, sem pedido nem amostra;
- nova versão gera só aviso e comparação;
- programas.

Mais a decisão de 2026-10-20: o dono vê **só números** das outras igrejas.

## Global Constraints

- Na igreja do Simon nada muda para aluno e professor: a IA, o áudio e o texto bíblico continuam ligados com os limites de hoje (`EBD_HELPER_DAILY_LIMIT`).
- Igreja nova nasce com a IA **desligada**, o áudio **desligado** e **sem** texto bíblico. Só o dono liga ("Uma igreja gastar a OpenAI do Simon sem ele liberar" é anti-objetivo).
- O texto da NAA segue a **igreja da lição**, nunca a de quem lê. A biblioteca oficial não mostra texto bíblico.
- O painel do dono mostra contagens e datas. Nunca mostra nomes de pessoas, títulos de lições, perguntas nem anotações de outra igreja.
- A cópia nunca é atualizada automaticamente. Ajustar a cópia é manual; "marcar como vista" só apaga o aviso.
- Testes sem rede (`Http::preventStrayRequests`). As migrações rodam sem parar o app.
- Os textos de interface seguem o padrão atual (português simples, sem jargão). A marca vem de `config/brand.php`.
- Não mexer no prompt teológico da IA (`AnswerStudyQuestion::instructionsFor`). Só muda **se** o texto bíblico entra no contexto.

## Review Focus

1. **Texto bíblico vazando para outra igreja** por um caminho esquecido: `/biblia` sem contexto, leituras da semana, lembrete push, texto de compartilhar, splash, prévia do admin, contexto da IA. Todos precisam seguir a igreja da lição (teste por caminho, na task D1-3).
2. **Cópia que aponta para o original:** material com arquivo da cópia apagado junto com o original, slug repetido, encontro ou leitura ainda presos ao original. A cópia precisa sobreviver ao original ser editado ou apagado (task D2-3).
3. **Versão nova que altera a cópia** sem a igreja pedir, ou aviso que não some depois de "marcar como vista" (task D2-4).
4. **Gasto de IA sem liberação:** uma igreja nova consegue gerar áudio ou perguntar pela rota direta, pelo MCP ou numa lição copiada (tasks D1-2 e D2-3).
5. **Painel do dono expondo conteúdo** por uma prop a mais, ou acessível a admin de igreja (task D1-4).

---

## D1 — Configurações por igreja e painel do dono (PR 1)

### Task D1-1: colunas de configuração e backfill

**Files:**

- Create: `database/migrations/2026_10_23_000100_add_settings_to_churches.php`
- Create: `database/migrations/2026_10_23_000200_create_ai_usages_table.php`
- Modify: `app/Models/Church.php`, `database/factories/ChurchFactory.php`, `app/Actions/Churches/CreateChurchWithAdmin.php` (só confirmar o padrão)
- Test: `tests/Feature/Migrations/ChurchSettingsMigrationTest.php`

**Interfaces:**

- Produces:
    - `churches.helper_enabled bool default false`;
    - `churches.helper_daily_limit smallint default 10`;
    - `churches.audio_enabled bool default false`;
    - `churches.audio_monthly_limit smallint default 4`;
    - `churches.shows_bible_text bool default false`;
    - tabela `ai_usages(id, church_id fk cascade, lesson_id nullable fk nullOnDelete, kind varchar check in ('question','audio'), created_at)`, com índice `(church_id, kind, created_at)`;
    - os casts no model `Church`;
    - `AiUsage` model com `AiUsageKind` enum (`Question = 'question'`, `Audio = 'audio'`).

- [ ] **Step 1: teste da migração**: monta duas igrejas (a principal e outra) antes da migração. Depois dela:
    - a principal tem `helper_enabled=true`, `audio_enabled=true`, `shows_bible_text=true` e `helper_daily_limit = config('ebd.helper.daily_limit')`;
    - a outra tem tudo `false`;
    - `ai_usages` recebe o histórico: uma linha `question` por `study_questions` existente (com o `church_id` da turma da lição e o `created_at` da pergunta) e uma linha `audio` por lição com `audio_generated_at`.
- [ ] **Step 2:** rodar o teste e ver a falha (colunas inexistentes).
- [ ] **Step 3:** migrações. O backfill da principal usa `Church::primary()`. A configuração atual do `.env` vira o valor inicial dela; as outras ficam com os padrões. `audio_monthly_limit` da principal = 30.
- [ ] **Step 4:** teste verde e suíte de migrações.
- [ ] **Step 5:** commit `feat: configurações de IA e texto bíblico por igreja`.

### Task D1-2: IA respeita a igreja

**Files:**

- Create: `app/Support/ChurchFeatures.php`
- Modify:
    - `app/Actions/Lessons/AnswerStudyQuestion.php` (`isAvailable`, `remainingToday`, `handle`);
    - `app/Http/Controllers/LessonController.php::helper`;
    - `app/Http/Controllers/StudyQuestionController.php`;
    - `app/Actions/Lessons/RequestLessonAudio.php`, `app/Http/Resources/LessonAudioResource.php::manage`;
    - `resources/js/components/admin/lesson-audio-manager.tsx`.
- Test: `tests/Feature/StudyHelper/ChurchAiSwitchTest.php`, `tests/Feature/LessonAudio/ChurchAudioSwitchTest.php`

**Interfaces:**

- Produces, em `ChurchFeatures` (estático, recebe a lição e lê `lesson->classroom->church`):
    - `helperAvailable(Lesson $lesson): bool`, que é `config('ebd.helper.enabled') && OpenAI configurada && church.helper_enabled`;
    - `helperDailyLimit(Lesson $lesson): int`;
    - `audioAvailable(Lesson $lesson): bool`;
    - `audioRemainingThisMonth(Lesson $lesson): int`, que é o limite menos as linhas `audio` do mês civil no fuso da igreja;
    - `showsBibleText(?Lesson $lesson): bool` (null → false);
    - `AiUsage::record(Church, AiUsageKind, ?Lesson)`.
- `LessonAudioResource::manage` ganha `enabled: bool` e `remaining: int`.

- [ ] **Step 1: testes (RED).**
    - Igreja B com `helper_enabled=false`: `helper` da página da lição é `null` e `POST licoes/{slug}/duvidas` dá 403.
    - Com `helper_enabled=true` e `helper_daily_limit=2`: a 3ª pergunta do dia é recusada com a mensagem de limite atual.
    - Com `audio_enabled=false`: `POST admin/licoes/{lesson}/audio` dá 403 e nenhuma chamada HTTP sai.
    - Com o limite mensal esgotado: o mesmo POST volta com erro "Limite de áudios do mês atingido". O limite conta só gerações que **terminaram** com sucesso, pela linha em `ai_usages` gravada em `GenerateLessonAudio`.
    - Na igreja principal, o comportamento atual não muda (os testes atuais de dúvidas e áudio continuam passando).
    - A pergunta grava `ai_usages` do tipo `question`.
- [ ] **Step 2:** ver falhar.
- [ ] **Step 3:** implementar.
    - `AnswerStudyQuestion` deixa de ler `daily_limit` da config e lê da igreja.
    - O botão "Gerar áudio" some quando `enabled=false`. Quando acaba o limite do mês, mostra "Limite de áudios do mês atingido (N por mês)".
    - As ferramentas do MCP não geram áudio nem perguntam: nada a mudar lá, só um teste confirmando que `save_lesson_*` não dispara IA.
- [ ] **Step 4:** testes verdes e suíte completa.
- [ ] **Step 5:** commit `feat: IA e áudio só onde o dono liberou`.

### Task D1-3: texto bíblico segue a igreja da lição

**Files:**

- Modify:
    - `app/Http/Resources/LessonResource.php` (`key_verse_passage`, `bible_passage`);
    - `app/Http/Resources/LessonReadingResource.php` (`passage`);
    - `app/Queries/StudyWeekQuery.php`;
    - `app/Http/Controllers/BibleController.php`;
    - `app/Http/Controllers/Admin/BiblePreviewController.php`;
    - `app/Support/WeeklyMessage.php` (`keyVerse`);
    - `app/Actions/Notifications/SendReadingReminders.php` (corpo do push);
    - `app/Support/SplashVerse.php`, `resources/views/partials/splash.blade.php`;
    - `app/Actions/Lessons/AnswerStudyQuestion.php::context`;
    - `resources/js/components/lesson/rich-text.tsx` (envia `licao` no fetch).
- Test: `tests/Feature/Bible/BibleTextByChurchTest.php`

**Interfaces:**

- Consumes: `ChurchFeatures::showsBibleText(?Lesson)`.
- `GET /biblia?ref=…&licao={slug}`:
    - sem `licao`, ou com lição de igreja sem licença: `{passages:[{reference, passage:null}]}`;
    - com lição de igreja licenciada: como hoje.
- O `LessonReadingResource` recebe a lição pelo relacionamento já carregado (sem N+1). A prévia do admin decide pela turma da lição em edição (`?licao=`).

- [ ] **Step 1: testes (RED).** Uma lição publicada da igreja B (sem licença) com `bible_reference`, `key_verse` e uma leitura:
    - a página `/licoes/{slug}` tem `key_verse_passage`, `bible_passage` e `readings.*.passage` nulos;
    - `/biblia?ref=Jo 3.16&licao={slug}` devolve `passage:null`, e o mesmo vale sem `licao`;
    - `my-week` de um aluno de B não traz texto;
    - o push de lembrete de B não contém o versículo;
    - o texto de compartilhar (`shareText`) só traz a referência;
    - o contexto enviado à IA (com B liberada para dúvidas e `Http::fake`) não contém o texto do `FakeBible`;
    - o splash não mostra versículo para usuário logado de B;
    - a lição da igreja principal continua mostrando o texto **também para visitante anônimo** (regressão).
- [ ] **Step 2:** ver falhar.
- [ ] **Step 3:** implementar.
    - O `Cache-Control` público de `/biblia` continua: a resposta já depende de `licao` na URL, então o cache por URL fica correto.
    - Quando não há texto, o front mostra só a referência. Os componentes já tratam `passage` nulo; conferir `key-verse.tsx`, `passage-text.tsx` e `reading-sheet.tsx`.
- [ ] **Step 4:** testes verdes, suíte, `tsc` e `vp check`.
- [ ] **Step 5:** commit `feat: texto bíblico só na igreja com licença`.

### Task D1-4: painel do dono (só números)

**Files:**

- Create:
    - `app/Http/Controllers/Owner/ChurchController.php` (`index`, `update`);
    - `app/Http/Requests/Owner/ChurchSettingsRequest.php`;
    - `app/Queries/OwnerChurchStats.php`;
    - `resources/js/pages/owner/churches.tsx`.
- Modify:
    - `app/Providers/AppServiceProvider.php` (Gate `own-platform` → `$user->isAdmin()`);
    - `routes/web.php` (grupo `dono`, `['auth','can:own-platform']`);
    - `app/Http/Middleware/HandleInertiaRequests.php` (`auth.user.is_owner`);
    - `resources/js/layouts/admin-layout.tsx` (item "Igrejas" só para o dono).
- Test: `tests/Feature/Owner/OwnerPanelTest.php`

**Interfaces:**

- `GET /dono/igrejas` → `owner/churches`, com a prop `churches`. Cada item tem:
    - `id`, `name`, `created_at`;
    - `classrooms_count` e `people_count` (pessoas distintas em `classroom_user` das turmas da igreja);
    - `last_activity_at` (o maior entre `reading_checkins.created_at`, `attendances.created_at` e `lessons.updated_at` da igreja);
    - `questions_this_month` e `audios_this_month` (de `ai_usages`);
    - `helper_enabled`, `helper_daily_limit`, `audio_enabled`, `audio_monthly_limit` e `shows_bible_text`.
- `PUT /dono/igrejas/{church}` com esses 5 campos, validados (limites entre 1 e 100). Grava em `AuditLog`.
- O nome da igreja aparece porque é como o dono identifica cada uma; nada dentro dela aparece.

- [ ] **Step 1: testes (RED).**
    - O admin de igreja (não dono) recebe 403 em `GET` e `PUT`.
    - O professor também recebe 403.
    - O dono vê as duas igrejas com contagens certas, num cenário com 2 turmas, 3 pessoas, 1 pergunta e 1 áudio no mês e 1 do mês anterior.
    - A prop não tem as chaves `lessons`, `users`, `questions` nem `title` (assert pela lista exata de chaves por item).
    - O `PUT` liga a IA da igreja B, e a página da lição de B passa a ter `helper` não nulo.
    - O `PUT` com limite 0 dá erro de validação.
- [ ] **Step 2:** ver falhar.
- [ ] **Step 3:** implementar. A tela é uma tabela simples, com chaves liga/desliga e campos numéricos, no estilo do painel atual. No celular vira cartões. Aviso fixo ao lado de "Texto bíblico": "Ligue só para igrejas com licença da NAA."
- [ ] **Step 4:** testes verdes, suíte, `tsc`, `vp check` e Larastan.
- [ ] **Step 5:** commit `feat: painel do dono com números por igreja`.

### Task D1-5: entrega

- [ ] Revisão do branch inteiro por subagente (opus, `code-reviewer.md`) e segunda opinião do Codex no isolamento.
- [ ] Validação no navegador: igreja nova sem botão de dúvida, sem áudio e sem texto; liga pelo `/dono` e aparece.
- [ ] PR para a `main`, merge com CI verde, acompanhar o deploy. Conferir em produção, só leitura, que a igreja principal ficou com tudo ligado.

---

## D2 — Biblioteca oficial, cópia e aviso de versão (PR 2)

### Task D2-1: organização oficial

**Files:**

- Create:
    - `database/migrations/2026_10_24_000100_create_official_library.php`;
    - `app/Support/OfficialLibrary.php`.
- Modify: `app/Models/Church.php` (`is_official` cast, `scopeOrdinary`, `official()`), `app/Queries/OwnerChurchStats.php` (sem a oficial).
- Test: `tests/Feature/Migrations/OfficialLibraryMigrationTest.php`

**Interfaces:**

- `churches.is_official bool default false`, com índice único parcial `WHERE is_official`.
- A migração cria:
    - a igreja "Biblioteca {brand.name}" (`is_official=true`, `shows_bible_text=false`, IA desligada);
    - o programa "Acervo";
    - a turma "Estudos oficiais" (slug `estudos-oficiais`, sem alunos);
    - os usuários com `is_admin=true` como admins dela.
- `OfficialLibrary::church(): Church` e `OfficialLibrary::classroom(): Classroom`.
- `Church::idsVisibleTo` não muda. A oficial só aparece para o Simon, que é admin dela.

- [ ] Steps no padrão RED → GREEN. O teste confere a criação, que o Simon continua com `currentChurch()` = a igreja dele (a oficial tem id maior) e que a oficial não aparece no painel do dono. Commit `feat: biblioteca oficial`.

### Task D2-2: publicar versão (retrato imutável)

**Files:**

- Create:
    - `database/migrations/2026_10_24_000200_create_series_versions_table.php`;
    - `app/Models/SeriesVersion.php`;
    - `app/Actions/Library/PublishSeriesVersion.php`;
    - `app/Support/Library/SeriesSnapshot.php`;
    - `app/Http/Controllers/Admin/SeriesVersionController.php`.
- Modify: `resources/js/pages/admin/series/form.tsx` (bloco "Biblioteca oficial": versão atual, botão "Publicar versão N" e nota da versão), `routes/web.php`.
- Test: `tests/Feature/Library/PublishSeriesVersionTest.php`

**Interfaces:**

- Tabela `series_versions`: `id`, `series_id` (fk cascade), `version` (int), `notes` (text null), `snapshot` (jsonb), `published_by` (fk users nullOnDelete), `created_at`. Único em `(series_id, version)`.
- `SeriesSnapshot::of(Series): array`. Formato:
    - série: `{title, description, lessons:[…]}`;
    - cada lição: `{source_lesson_id, number, title, summary, bible_reference, key_verse, goal, content, visibility, blocks:[…], readings:[…], materials:[…]}`;
    - blocos: `{kind, audience, title, body, drip_weekday, position}`;
    - leituras: `{weekday, reference, notes, position}`;
    - materiais: `{type, audience, title, description, url, is_primary, position, file:{disk,path,original_name,mime_type,size_bytes}|null}`.

    Só entram lições **publicadas** da série, em ordem de `number`.

- `PublishSeriesVersion::handle(User $by, Series $series, ?string $notes): SeriesVersion`:
    - a série precisa estar na turma oficial;
    - `version` = máximo + 1;
    - arquivos de material são **copiados** para `library/{series_id}/v{version}/`, para o retrato não depender do original.
- `POST admin/series/{series}/versoes` só para o dono (`own-platform`); para outra série, 404.

- [ ] **Step 1: testes (RED).**
    - O dono publica: a versão 1 tem as lições publicadas e não tem o rascunho.
    - Editar a lição depois não muda o retrato da versão 1.
    - O arquivo do material existe no caminho da versão (`Storage::fake`).
    - Apagar o material original não apaga o arquivo da versão.
    - Admin de outra igreja recebe 403.
    - Série de turma comum dá 404.
    - Série oficial sem nenhuma lição publicada volta com erro "Publique ao menos uma lição antes".
- [ ] Steps 2–5 no padrão. Commit `feat: publicar versão de um estudo oficial`.

### Task D2-3: biblioteca oficial e "Usar numa turma" (cópia)

**Files:**

- Create:
    - `database/migrations/2026_10_24_000300_add_source_to_series_and_lessons.php`;
    - `app/Actions/Library/CopySeriesToClassroom.php`;
    - `app/Http/Controllers/Admin/OfficialLibraryController.php` (`index`, `show`, `copy`);
    - `resources/js/pages/admin/library/index.tsx` e `show.tsx`.
- Modify: `resources/js/layouts/admin-layout.tsx` (item "Biblioteca oficial" para quem tem `access-admin`), `routes/web.php`.
- Test: `tests/Feature/Library/CopySeriesTest.php`, `tests/Feature/Library/OfficialLibraryPageTest.php`

**Interfaces:**

- `series.source_series_id` (fk series nullOnDelete) e `series.source_version` (int null).
- `lessons.source_lesson_id` (fk lessons nullOnDelete).
- `CopySeriesToClassroom::handle(User $by, SeriesVersion $version, Classroom $target): Series`:
    - autoriza `manageContent` no destino;
    - cria a série com o título da versão (slug pelo `CreateSeries::uniqueSlug`);
    - cria cada lição como **rascunho** (status draft), com slug por `GenerateLessonSlug`, `created_by` = quem copiou e `source_lesson_id`;
    - copia blocos, leituras e materiais;
    - arquivos copiados para `materials/{nova lição}/`;
    - nenhum áudio é copiado;
    - `SyncLessonSearchText` em cada lição;
    - grava `AuditLog`;
    - tudo numa transação. Se a cópia de arquivo falhar, os arquivos já copiados são apagados.
- Páginas:
    - `GET admin/biblioteca-oficial`: séries oficiais com pelo menos uma versão, mostrando título, descrição, nº de lições, versão e data;
    - `GET admin/biblioteca-oficial/{series}`: as lições da última versão, lidas do retrato, em leitura com o mesmo `RichText` da lição. **Sem texto bíblico**, só a referência;
    - `POST admin/biblioteca-oficial/{series}/usar` com `classroom_id` entre as turmas em que a pessoa pode `manageContent`. Redireciona para a série copiada com o aviso "Estudo copiado. As lições estão em rascunho: revise e publique quando quiser."

- [ ] **Step 1: testes (RED).**
    - O professor da igreja B copia para a turma dele: a série e as lições são da turma B, em rascunho, com o mesmo conteúdo do retrato e `source_*` preenchidos.
    - Professor de B tentando copiar para uma turma da igreja do Simon recebe 403 (ou validação).
    - A cópia não tem áudio.
    - O arquivo do material existe no caminho da lição nova.
    - Apagar a lição oficial não apaga nada da cópia.
    - Copiar duas vezes para a mesma turma gera slugs diferentes, sem erro.
    - Na igreja B sem IA, a lição copiada continua sem dúvida e sem áudio (Review Focus 4).
    - Nenhum push de "lição publicada" sai na cópia (as lições nascem rascunho).
    - A página da biblioteca: aluno sem gestão recebe 403; admin de B vê a lista; a página de detalhe não contém o texto do `FakeBible`.
- [ ] Steps 2–5 no padrão. Commit `feat: usar estudo oficial numa turma`.

### Task D2-4: aviso de versão nova e comparação

**Files:**

- Create:
    - `app/Support/TextDiff.php`;
    - `app/Queries/SeriesUpdateComparison.php`;
    - `app/Http/Controllers/Admin/SeriesUpdateController.php` (`show`, `acknowledge`);
    - `resources/js/pages/admin/series/update.tsx`.
- Modify: `app/Http/Controllers/Admin/SeriesController.php` (index e edit mandam `update_available: {from, to} | null`), `resources/js/pages/admin/series/index.tsx` e `form.tsx` (selo "Versão nova disponível"), `routes/web.php`.
- Test: `tests/Unit/TextDiffTest.php`, `tests/Feature/Library/SeriesUpdateTest.php`

**Interfaces:**

- `TextDiff::lines(?string $old, ?string $new): list<array{type: 'same'|'added'|'removed', text: string}>`: diff por linha, com LCS e sem dependência nova.
- `SeriesUpdateComparison::for(Series $copy): ?array` compara o retrato `source_version` com o último:
    - devolve `{from, to, notes:[…], lessons:[{number, title, status:'added'|'removed'|'changed', fields:[{name, label, diff}]}]}`;
    - cada lição é casada por `source_lesson_id`;
    - entram os campos de texto (título, resumo, referência, versículo-chave, objetivo, conteúdo) e os blocos casados por posição e tipo;
    - leituras e materiais aparecem como "mudou: N itens".
- `GET admin/series/{series}/atualizacao` → página com a comparação (lado a lado no desktop, empilhada no celular).
- `POST admin/series/{series}/atualizacao/vista` → `source_version = última`. Não toca em nenhuma lição.
- Autorização: `update` na série (`SeriesPolicy`).

- [ ] **Step 1: testes (RED).**
    - `TextDiff`:
        - texto igual dá tudo `same`;
        - uma linha trocada dá um `removed` e um `added`;
        - null contra texto dá tudo `added`.
    - Feature:
        - cópia na v1, dono publica a v2 com o conteúdo da lição 2 alterado e uma lição nova: a cópia mostra `update_available {from:1,to:2}`, e a comparação lista a lição 2 como `changed` (com o diff) e a nova como `added`;
        - **o conteúdo da cópia não mudou**;
        - "marcar como vista" zera o aviso e não muda nada nas lições;
        - a v3 faz o aviso voltar;
        - admin de outra igreja recebe 403 na comparação da cópia de B;
        - série sem origem dá 404 na comparação.
- [ ] Steps 2–5 no padrão. Commit `feat: aviso e comparação de versão nova`.

### Task D2-5: levar _Fora de Contexto_ para a biblioteca

**Files:**

- Create: `app/Console/Commands/CopySeriesToOfficialLibrary.php` (`didache:copiar-para-biblioteca {series}`).
- Test: `tests/Feature/Library/CopySeriesToOfficialLibraryCommandTest.php`

**Interfaces:**

- O comando **copia** a série (não move) da turma do Simon para a turma oficial. As lições ficam em rascunho, sem `source_*`. Reaproveita a parte de cópia de conteúdo de `CopySeriesToClassroom`, extraída para `App\Support\Library\LessonContentCopier`.
- A original fica intacta na igreja do Simon. Depois o Simon revisa (as lições da revista não são da biblioteca: o comando avisa "confira se o texto não cola versículos da NAA nem trechos da revista"), publica as lições e publica a versão 1.
- O comando só roda em produção com a confirmação do Simon. Fica para depois do merge e não roda automaticamente.

- [ ] Steps no padrão. O teste confere que a série original não mudou e que a cópia está na turma oficial. Commit `feat: comando para levar um estudo à biblioteca`.

### Task D2-6: entrega

- [ ] Revisão do branch inteiro (opus) e Codex.
- [ ] Navegador: publicar a v1 como dono, copiar como admin de uma igreja nova, publicar a v2 e ver o aviso e a comparação, marcar como vista.
- [ ] PR para a `main`, merge com CI verde e deploy acompanhado. Em produção, só leitura: a igreja oficial foi criada e a do Simon não mudou.
- [ ] Depois do deploy, **perguntar** ao Simon antes de rodar o comando da task D2-5 em produção.

## Fora desta etapa (registrado para depois)

- Tela de programas e coordenadores.
- Seletor de igreja para quem administra mais de uma.
- Fuso por igreja na tela.
- Limites de cadastro (etapa F).
- Site institucional e Pix (etapa E).
- Aplicar a versão nova na cópia ("mesclar"), que a missão deixou fora.
