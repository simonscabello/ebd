# Entrega 2 — Agenda por classe Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Cada classe escolhe o dia da semana do encontro (padrão domingo). A agenda, a semana de leitura, os lembretes e os textos principais passam a seguir esse dia. Para classes de domingo, nada muda.

**Architecture:**

- Coluna `classrooms.meeting_weekday` (ISO 1..7, padrão 7), com cast para o enum `Weekday`.
- A semana de leitura deixa de ser "segunda a domingo" e passa a ser **os 6 dias antes do encontro até o dia do encontro**, calculada a partir da data do encontro. Para domingo, o resultado é idêntico ao de hoje.
- `lesson_readings.weekday` e `reading_checkins.weekday` continuam sendo o dia ISO do calendário. **Nenhum dado existente muda.**
- A geração de encontros (`FillSundays`, `PlanMeetings`, `ContinueLessonNextMeeting`) usa o dia da classe.
- O "dia do encontro" deixa de ser a constante 7 e passa a ser o dia ISO do encontro.

**Tech Stack:** Laravel 13, PHP 8.4, PostgreSQL, PHPUnit 12, Inertia 3/React 19, Wayfinder.

**Spec:** `docs/superpowers/specs/2026-10-19-plataforma-multi-igreja-design.md` (seções "Agenda por classe" e "Telas e fluxos novos" item 5).

## Global Constraints

- Na igreja do Simon, nada muda para aluno e professor. Todas as classes são de domingo, e nos 16 encontros de produção não há nenhum fora do domingo (conferido em 2026-10-19).
- Uma classe nova continua sendo de domingo por padrão.
- O fuso continua vindo de `config('ebd.timezone')` (`ChurchCalendar::timezone()`). O fuso por igreja fica para a entrega 3, junto com o cadastro de igrejas, porque hoje só existe uma.
- Não renomear rotas, classes nem tipos que o usuário não vê (`FillSundays`, `/domingos`, `SundaySummary`, `sunday.tsx`). Renomear seria só risco. A mudança é de comportamento e de texto visível.
- Testes nunca usam rede. PR com `--base main`. Rodar `npx vp check --fix` antes do push.
- Texto de interface em português, no tom atual.

## Review Focus

1. **Trocar o dia de uma classe com encontros futuros já planejados.** Esperado: os encontros futuros ainda **planejados e sem chamada** mudam para o novo dia dentro da mesma semana de leitura, mantendo lição e ordem. Encontros passados, realizados, cancelados ou com chamada não mudam. Teste na Task 1.
2. **Aula numa quarta, hoje é quinta.** A "leitura de hoje" é a do plano para quinta, da lição do próximo encontro (a quarta seguinte). O plano tem 7 dias, de quinta a quarta, e a quarta é o dia da aula. Teste na Task 3.
3. **Classe de domingo** (todos os testes existentes, com datas fixas em domingo). A suíte inteira continua verde sem alterar asserções de datas. Se algum teste de domingo precisar mudar, é regressão, não ajuste.
4. **Véspera do encontro.** O aviso "Amanhã tem EBD!" sai às 8h do dia anterior ao encontro de cada classe: sábado para domingo, terça para quarta. Nunca sai duas vezes no mesmo dia. Teste na Task 4.
5. **Data de encontro fora do dia da classe** (form, "Planejar", MCP `save_meeting`, data da lição). Esperado: erro de validação claro em português ("Esta classe se reúne às quartas-feiras."), não um encontro num dia errado. Teste na Task 2.

---

## Preparação

- [ ] Partir da `main` atualizada: `git switch main && git pull && git switch -c feat/agenda-por-classe`. Commitar este plano (`docs: plano da entrega 2`).

### Task 1: Dia do encontro na classe

**Files:**

- Create:
    - `database/migrations/2026_10_21_000100_add_meeting_weekday_to_classrooms.php`;
    - `app/Actions/Classrooms/ChangeMeetingWeekday.php`.
- Modify:
    - `app/Models/Classroom.php` (cast e `@property Weekday $meeting_weekday`);
    - `app/Http/Requests/Admin/ClassroomRequest.php`;
    - `app/Actions/Classrooms/CreateClassroom.php`;
    - o update de classe em `app/Http/Controllers/Admin/ClassroomController.php`;
    - o formulário de classe em `resources/js/pages/admin/classrooms/` (create/edit; localizar com `grep -rln "ClassroomRequest\|is_active" resources/js/pages/admin/classrooms`);
    - `app/Http/Resources/ClassroomResource.php`;
    - `database/factories/ClassroomFactory.php`.
- Test: `tests/Feature/Admin/ClassroomScheduleTest.php` (novo).

**Interfaces:**

- Produces:
    - coluna `classrooms.meeting_weekday smallint not null default 7`, com `CHECK BETWEEN 1 AND 7`;
    - `Classroom::$meeting_weekday` como `App\Enums\Weekday`;
    - `ChangeMeetingWeekday::handle(Classroom $classroom, Weekday $weekday): int` (devolve quantos encontros foram remarcados);
    - `ClassroomResource` expõe `meeting_weekday` (int) e `meeting_weekday_label` (`Weekday::label()`, ex.: "Quarta-feira").

- [ ] **Step 1: Escrever os testes que falham** (`ClassroomScheduleTest`, num domingo fixo com `travelTo` em 2026-09-27 10h, America/Sao_Paulo):
    - `test_new_classrooms_meet_on_sunday_by_default`: `Classroom::factory()->create()->meeting_weekday === Weekday::Sunday`. O admin cria "Casais" sem informar o dia → `Weekday::Sunday`.
    - `test_admin_sets_the_meeting_weekday`: o admin cria uma classe com `meeting_weekday = 3` → `Weekday::Wednesday`. Valor 8 → erro em `meeting_weekday`.
    - `test_changing_the_day_moves_planned_future_meetings_within_their_week`:
        - Classe de domingo com encontros `2026-09-20` (realizado, com chamada), `2026-10-04` (planejado, com lição A), `2026-10-11` (cancelado) e `2026-10-18` (planejado, com lição B).
        - Trocar para quarta.
        - Esperado: `2026-10-04` vira `2026-09-30`, ainda com a lição A; `2026-10-18` vira `2026-10-14`, com a lição B; `2026-09-20` e `2026-10-11` não mudam. O retorno é `2`.
        - Regra: a nova data é o dia escolhido dentro da janela `(data − 6 … data)`. Se ela cair hoje ou antes, usa o mesmo dia da semana seguinte.
        - `lessons.scheduled_for` acompanha a mudança (via `SyncLessonSchedule`).
    - `test_teacher_cannot_change_the_meeting_day`: um professor que não é admin recebe 403 ao atualizar a classe, mantendo a regra de autorização atual de update de classe.

- [ ] **Step 2: Rodar e ver falhar.** `DB_PORT=5432 php artisan test --filter=ClassroomScheduleTest`. Esperado: FAIL (coluna inexistente).

- [ ] **Step 3: Implementar.**
    - A migration adiciona a coluna com default 7, o que já serve para as classes existentes.
    - Validação: `meeting_weekday` é `sometimes|Rule::enum(Weekday::class)`, com atributo "dia do encontro".
    - O update chama `ChangeMeetingWeekday` só quando o valor mudou, dentro de uma transação, e mostra o toast "Encontros remarcados para {label}." quando o retorno for maior que 0.
    - No formulário, um select "Dia do encontro" com as 7 opções de `Weekday::label()`, padrão Domingo.

- [ ] **Step 4: Rodar e ver passar**, depois a suíte inteira. Esperado: tudo verde.

- [ ] **Step 5: Commit.** `feat: classe escolhe o dia do encontro`.

### Task 2: Geração e validação dos encontros pelo dia da classe

**Files:**

- Modify:
    - `app/Support/ChurchCalendar.php` (substituir `nextSunday()`);
    - `app/Actions/Meetings/FillSundays.php`;
    - `app/Actions/Meetings/PlanMeetings.php`;
    - `app/Actions/Meetings/ContinueLessonNextMeeting.php` (`freeSundayAfter`);
    - `app/Concerns/MeetingValidationRules.php`;
    - `app/Http/Requests/Admin/LessonRequest.php`;
    - os chamadores de `nextSunday`: `Admin/ClassMeetingController.php:68`, `Admin/LessonController.php:99`, `database/seeders/LessonContentSeeder.php:34`;
    - as ferramentas MCP `SaveMeetingTool`, `PlanMeetingsTool` e `SaveLessonDraft` (validação e textos de descrição);
    - `app/Mcp/Servers/EbdServer.php:51` (instrução "Os encontros da EBD são aos domingos" → "Cada classe se reúne num dia da semana (veja list_classrooms); datas fora desse dia são recusadas.").
- Test:
    - `tests/Unit/ChurchCalendarTest.php` (novo);
    - `tests/Feature/Admin/MeetingManagementTest.php` (casos novos);
    - `tests/Feature/Mcp/McpMeetingToolsTest.php` (um caso novo).

**Interfaces:**

- Consumes: `Classroom::$meeting_weekday` (Task 1).
- Produces:
    - `ChurchCalendar::nextMeetingDay(Weekday $weekday): CarbonImmutable`: hoje, se hoje for o dia; senão o próximo. Substitui `nextSunday()`, que é removido.
    - `ChurchCalendar::onOrAfter(\DateTimeInterface $date, Weekday $weekday): CarbonImmutable`: o primeiro `$weekday` a partir de `$date`, inclusive.
    - `MeetingValidationRules::meetingDayRule(Classroom $classroom)`: uma regra (closure) que recusa datas fora do dia, com a mensagem "Esta classe se reúne {às quartas-feiras | aos domingos | aos sábados}.". Usar a forma plural correta: "aos sábados" e "aos domingos"; "às" para os dias "-feira".

- [ ] **Step 1: Escrever os testes que falham.**
    - `ChurchCalendarTest::test_next_meeting_day` (com `travelTo` numa quinta, 2026-10-01): `nextMeetingDay(Weekday::Thursday)` = `2026-10-01`; `nextMeetingDay(Weekday::Wednesday)` = `2026-10-07`; `nextMeetingDay(Weekday::Sunday)` = `2026-10-04`.
    - `MeetingManagementTest`:
        - `test_fill_creates_meetings_on_the_classroom_day`: classe de quarta, sem encontros, hoje 2026-10-01 → depois de `FillSundays`, todos os `held_on` têm `dayOfWeekIso === 3`, o primeiro é `2026-09-30` (a quarta desta semana, o mesmo critério do "domingo desta semana" de hoje) e há `WEEKS_AHEAD + 1` encontros.
        - `test_plan_meetings_uses_the_classroom_day`: "Planejar" de 2026-10-01 a 2026-10-31 numa classe de quarta → 2026-10-07, 14, 21 e 28.
        - `test_meeting_on_another_day_is_rejected`: criar um encontro em 2026-10-04 (domingo) numa classe de quarta → erro em `held_on` com "Esta classe se reúne às quartas-feiras.". Criar uma lição com `meeting_on` = domingo na mesma classe → erro em `meeting_on`.
        - `test_continue_creates_the_next_meeting_on_the_classroom_day`: classe de quarta, encontro único 2026-09-30 com lição, encerrar com "continua" → novo encontro em 2026-10-07.
    - `McpMeetingToolsTest::test_save_meeting_rejects_a_day_outside_the_classroom_day`.

- [ ] **Step 2: Rodar e ver falhar.**

- [ ] **Step 3: Implementar.**
    - Trocar todo `isSunday()` / `next(SUNDAY)` / `previous(SUNDAY)` desses arquivos pelo dia da classe. "Domingo desta semana" vira "o dia da classe na janela de hoje − 6 até hoje".
    - Os docblocks passam a dizer "dia do encontro".
    - A regra `meetingDayRule` entra em `MeetingValidationRules` (criar e editar encontro, `meeting_on` da lição e as ferramentas MCP).

- [ ] **Step 4: Rodar os testes e a suíte inteira.** As classes de domingo dos testes existentes não podem mudar de comportamento.

- [ ] **Step 5: Commit.** `feat: encontros gerados e validados pelo dia da classe`.

### Task 3: Semana de leitura a partir da data do encontro

**Files:**

- Modify:
    - `app/Support/ChurchCalendar.php` (`readingWeekStart`, docblock de `readingWeekday`);
    - `app/Queries/StudyWeekQuery.php` (`:51-52`, `:87-107`, `:135-150`);
    - `app/Actions/Engagement/AwardBadges.php:88,97-113`;
    - `app/Actions/Notifications/SendReadingReminders.php:139`;
    - `app/Enums/Badge.php:30` (texto "segunda a sábado" → "todos os dias antes do encontro");
    - `resources/js/pages/my-week.tsx:132`, que filtra `weekday <= 6` e passa a usar um campo `is_meeting_day` vindo do servidor.
- Test:
    - `tests/Unit/ChurchCalendarTest.php`;
    - `tests/Feature/EngagementTest.php` (casos novos);
    - `tests/Feature/NotificationsTest.php` (um caso novo).

**Interfaces:**

- Consumes: `Classroom::$meeting_weekday`; os encontros já no dia certo (Task 2).
- Produces:
    - `ChurchCalendar::readingWeekStart(\DateTimeInterface $meetingOn): CarbonImmutable` = `$meetingOn − 6 dias`. Para domingo, isso é a segunda, igual a hoje.
    - Cada dia do `StudyWeekQuery` ganha `is_meeting_day: bool`.
    - O dia do encontro é `CarbonImmutable::parse(held_on)->dayOfWeekIso`, nunca a constante 7 / `Weekday::Sunday`.

- [ ] **Step 1: Escrever os testes que falham.**
    - `ChurchCalendarTest::test_reading_week_ends_on_the_meeting_day`:
        - `readingWeekStart('2026-10-07')` (quarta) = `2026-10-01` (quinta).
        - `readingWeekStart('2026-10-04')` (domingo) = `2026-09-28` (segunda).
        - `readingWeekday('2026-10-07', today: '2026-10-02')` = 5 (sexta).
        - `readingWeekday('2026-10-07', today: '2026-09-30')` = null, porque é o dia da aula anterior e fica fora da janela.
    - `EngagementTest::test_wednesday_class_week_runs_thursday_to_wednesday`:
        - classe de quarta, lição com leituras nos dias 4, 5, 6, 7, 1, 2 e 3; encontro em 2026-10-07; hoje 2026-10-02.
        - A semana do `StudyWeekQuery` lista os dias em ordem de data, de quinta a quarta.
        - `is_today` cai na sexta; `is_meeting_day` só na quarta.
        - Marcar a leitura de hoje grava `weekday = 5`.
    - `EngagementTest::test_first_full_week_badge_counts_the_six_days_before_the_meeting`: na classe de quarta, 6 check-ins de quinta a terça dão o selo `FirstFullWeek`.
    - `NotificationsTest::test_no_reread_reminder_on_the_meeting_day_of_a_wednesday_class`: numa quarta de aula, sem lembrete de "releia o texto base", como hoje acontece no domingo.

- [ ] **Step 2: Rodar e ver falhar.**

- [ ] **Step 3: Implementar.**
    - `StudyWeekQuery` percorre as 7 datas de `readingWeekStart` até o encontro, com `weekday = dayOfWeekIso` de cada uma. Os dias de leitura são todos, menos o dia do encontro.
    - `dripSchedule` distribui os blocos automáticos entre esses 6 dias, na ordem das datas.
    - `AwardBadges` conta os 6 dias da semana de leitura daquela lição, excluindo o dia ISO do encontro.
    - `SendReadingReminders:139` compara com o dia do encontro.

- [ ] **Step 4: Rodar os testes e a suíte inteira.** Esperado: verde, sem mudar nenhum teste de domingo existente.

- [ ] **Step 5: Commit.** `feat: semana de leitura termina no dia do encontro`.

### Task 4: Lembrete da véspera para qualquer dia

**Files:**

- Modify: `routes/console.php:29`, `app/Actions/Notifications/SendLessonReminder.php` (só docblock, se a lógica já é "amanhã").
- Test: `tests/Feature/NotificationsTest.php`.

**Interfaces:** nenhuma nova.

- [ ] **Step 1: Escrever os testes que falham.**
    - `test_eve_reminder_goes_out_the_day_before_a_wednesday_meeting`: numa terça às 8h, uma classe de quarta com lição publicada recebe "Amanhã tem EBD!". Uma classe de domingo não recebe nada nesse dia.
    - `test_the_eve_reminder_is_scheduled_daily`: o evento `ebd:remind-lesson` no `Schedule` roda todo dia às 08:00. Inspecionar `app(Schedule::class)->events()` pelo comando.
- [ ] **Step 2: Rodar e ver falhar.**
- [ ] **Step 3: Implementar.** `->dailyAt('08:00')` no lugar de `saturdays()->at('08:00')`, mantendo o `->timezone($timezone)`.
- [ ] **Step 4: Rodar os testes e a suíte inteira.**
- [ ] **Step 5: Commit.** `feat: aviso de véspera no dia anterior ao encontro de cada classe`.

### Task 5: Textos que o aluno e o professor veem

**Files:**

- Modify:
    - `app/Http/Controllers/LessonController.php` (`sunday`/`conduct`, para expor o dia);
    - `resources/js/pages/lessons/sunday.tsx` (título "Modo Domingo");
    - `resources/js/pages/my-week.tsx:224-228` (contagem "faltam N dias para domingo");
    - `resources/js/pages/lessons/show.tsx:175,289,322` ("Um pouco por dia até domingo");
    - `app/Support/WeeklyMessage.php:30` ("Domingo, …");
    - `resources/js/components/admin/classroom-header.tsx:35-36` (aba "Domingos");
    - `resources/js/components/admin/lesson-form.tsx:208` ("Domingo da aula");
    - `app/Actions/Meetings/RecordAttendance.php:37` (erro "no dia do domingo");
    - `app/Http/Controllers/Admin/AttendanceController.php:44-48` (toast "Até domingo!");
    - `resources/js/types/ebd.ts` (os campos novos).
- Test: `tests/Feature/LessonAccessTest.php` ou `HomeTest.php` (asserções de props) e `tests/Unit` para o helper de texto.

**Interfaces:**

- Consumes: `Classroom::$meeting_weekday`.
- Produces:
    - `Weekday::meetingName(): string` → "Domingo" para domingo, "Encontro" para os outros dias. Usado em "Modo {meetingName}".
    - `Weekday::meetingNamePlural(): string` → "Domingos" ou "Encontros", para a aba da gestão.
    - `Weekday::lowerLabel(): string` → "domingo", "quarta-feira" etc., para frases como "faltam 3 dias para quarta-feira" e "Até quarta-feira!".
    - O objeto da classe enviado às páginas do aluno e da gestão ganha `meeting_weekday` (int), `meeting_day` (`lowerLabel`) e `meeting_name` (`meetingName`).

- [ ] **Step 1: Escrever os testes que falham.**
    - `WeekdayTest`: `Weekday::Sunday->meetingName() === 'Domingo'`; `Weekday::Wednesday->meetingName() === 'Encontro'`; `Weekday::Wednesday->lowerLabel() === 'quarta-feira'`; `Weekday::Sunday->meetingNamePlural() === 'Domingos'`; `Weekday::Wednesday->meetingNamePlural() === 'Encontros'`.
    - Teste de feature, classe de quarta: a página `/licoes/{slug}/domingo` tem a prop `conduct.meeting_name === 'Encontro'`. Com uma classe de domingo, `'Domingo'`.
    - `WeeklyMessage` para uma lição de quarta começa com "Quarta-feira, 07/10"; para domingo, continua "Domingo, 04/10".
- [ ] **Step 2: Rodar e ver falhar.**
- [ ] **Step 3: Implementar** os textos da lista de arquivos usando os campos novos. Em toda tela de uma classe de domingo, a saída precisa continuar **idêntica** à de hoje.
- [ ] **Step 4: Rodar os testes, `npx tsc --noEmit`, `npx vp check --fix` e a suíte inteira.**
- [ ] **Step 5: Commit.** `feat: textos do aluno e do professor seguem o dia da classe`.

### Task 6: Verificação e publicação

- [ ] **Step 1:** Rodar `DB_PORT=5432 php artisan test`, `vendor/bin/pint --test`, `vendor/bin/phpstan analyse`, `npx tsc --noEmit`, `npx vp check` (ignorar `.omc/`) e `npm run build`. Esperado: tudo verde.
- [ ] **Step 2: Navegador, no banco do laboratório (`ebd_lab`, porta 8011, Playwright no scratchpad).**
    - Como aluno de Jovens (domingo): o início, a lição, "Minha semana" e o Modo Domingo ficam iguais aos de antes. Comparar os textos com a `main` rodando antes da mudança.
    - Como admin: trocar a "Classe Persona" para quarta e conferir que os encontros futuros foram remarcados.
    - Como aluno dessa classe: "Minha semana" vai de quinta a quarta, e o título do modo é "Modo Encontro".
- [ ] **Step 3: Revisão final independente** (executing-plans), depois o PR com `gh pr create --base main` e o merge quando o Simon autorizar.
- [ ] **Step 4: Depois do deploy, conferir (só leitura).**
    - A migration aparece como `DONE` no log.
    - `select count(*) from classrooms where meeting_weekday <> 7` = 0.
    - As páginas públicas respondem 200.
    - Nenhum 500 nos logs.

## Fora desta entrega

- Fuso por igreja (entrega 3).
- Textos da página de Ajuda e dos relatórios internos que ainda dizem "domingo". Ficam como estão: na igreja do Simon estão corretos, e a revisão de textos para outras igrejas entra com o cadastro (entrega 3).
- Horário do encontro (exibição apenas). Não pedido; dá para acrescentar depois sem migração de dados difícil.
