# Etapa C — Programas e organizações Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Duas entregas, cada uma com o próprio PR:

- **C1:** programas de ensino. Organização → programa → turmas. A igreja do Simon ganha o programa "Escola Bíblica Dominical", com as turmas atuais.
- **C2:** qualquer pessoa cria a própria igreja pelo cadastro e a administra, com os dados **isolados** entre igrejas.

**Architecture:**

- **C1:** tabela `programs` (igreja, nome, nome curto) e `classrooms.program_id`. O coordenador de programa (`program_user`) gerencia todas as turmas do programa. Os textos que hoje dizem "EBD" no sentido de programa passam a usar o nome curto do programa da turma ("EBD" na igreja do Simon, então nada muda lá).
- **C2:** a autorização passa a girar em torno da igreja:
    - `canManageClassroom` = professor da turma ∪ coordenador do programa ∪ administrador da igreja;
    - o `is_admin` global deixa de dar acesso implícito ao conteúdo das igrejas (ver "Decisão pendente");
    - listagens, biblioteca e página inicial filtram pela igreja do contexto;
    - "adicionar por e-mail", o link pessoal e o "mover aluno" ficam presos à igreja;
    - o cadastro aberto vira "Criar minha igreja".

**Tech Stack:** Laravel 13, PHP 8.4, PostgreSQL, PHPUnit 12, Inertia 3/React 19, Fortify, `laravel/mcp`.

**Spec:**

- `docs/superpowers/specs/2026-10-19-plataforma-multi-igreja-design.md`;
- `docs/didache/00-diagnostico.md` (decisões do Simon, seção 7);
- o mapa de isolamento, resumido em "Superfície de isolamento" abaixo.

## Global Constraints

- **Na igreja do Simon, nada muda para aluno e professor.** O merge na `main` publica em produção.
- **O cadastro público vira "Criar minha igreja".** Alunos e professores entram por convite ou link pessoal (decisão de 2026-10-19).
- **Fuso:** continua o da configuração (`America/Sao_Paulo`) para todas as igrejas nesta etapa. O cadastro não pergunta o fuso. Ruling: todas as igrejas previstas estão no Brasil (SP). O fuso por igreja entra quando aparecer uma fora dele.
- **Slugs:** continuam globais (URLs não mudam). Em colisão, recebem sufixo, como hoje.
- **Link pessoal:** continua sendo a recuperação de acesso, que apaga a senha (regra do Simon). O que muda é **quem pode emitir:** só alguém que gerencia **todas** as turmas da pessoa. Isso fecha a tomada de conta entre igrejas (ADR-007, S-01).
- Testes nunca usam rede. PR com `--base main`. Rodar `npx vp check --fix` antes do push.

## Decisão do Simon (2026-10-20)

O dono do sistema (`is_admin`) **não** vê o conteúdo interno das outras igrejas (alunos, anotações, chamada, dúvidas). Ele administra a própria igreja como qualquer administrador. A visão das outras igrejas virá no painel do dono (etapa D), só com números agregados.

## Review Focus

1. **Professor da igreja A com o slug ou o ID da igreja B** em qualquer rota de `/admin`, `/licoes/{slug}` (rascunho ou "só membros"), material, áudio ou MCP → 403/404, sem efeito colateral.
2. **Uma pessoa com vínculo em duas igrejas** (aluna na A e na B): o professor da B não emite link, não edita os dados nem move a pessoa. Os dois professores continuam vendo a pessoa nas próprias turmas.
3. **Visitante anônimo e logado sem turma:** a página inicial e a `/biblioteca` não listam turmas nem lições de outra igreja. Uma lição pública continua abrindo pelo link.
4. **Cadastro "Criar minha igreja"** com e-mail já existente, com campos vazios e com logado tentando criar uma segunda igreja → validação clara. A nova igreja nasce com o criador como administrador e um programa padrão.
5. **Na igreja do Simon,** depois da troca do `is_admin` pelo papel de igreja, ele continua fazendo tudo o que faz hoje: criar turma, nomear professor, ver relatórios, MCP. Os testes atuais de `AdminAccessTest` e `Mcp/*` continuam passando.

## Superfície de isolamento (do mapa, 2026-10-19)

- **Autorização:**
    - `User::canManageClassroom` (`User.php:200`) e `manageableClassroomIds` (`:269-279`, `null` = todas para o admin);
    - `ClassroomPolicy` (`create`, `update` e `assignTeachers` = `isAdmin`);
    - `StudentNotePolicy:16`;
    - `UpdateStudentProfile:27`;
    - `StudentController:168`;
    - `ClassroomQuestionController:34`;
    - `Lesson::visibleTo` (`Lesson.php:255`, bypass do admin).
- **Listas globais:**
    - `ClassroomSelector:21` (início);
    - `LibraryController:26-31,41`;
    - `LibrarySearch:34-40` e `availableYears`;
    - `CurrentLessonQuery:89-90` (recentes);
    - listas de admin e dashboard com `when($manageable !== null)`.
- **Pessoas:**
    - `AddClassroomMember:19` (e-mail global, mensagem que revela se a conta existe);
    - `IssueAccessLink:29-35` e `LoginWithAccessLink:36-38`;
    - `MoveStudentToClassroom:18`, `StudentController:204`, `Mcp/Tools/MoveStudent:45`;
    - `UpdateStudentProfile:31-45`;
    - `MergeAccounts:39`.
- **Nome exibido:** `HandleInertiaRequests:49` e `ResetPasswordNotification:33` usam `Church::primary()`.
- **Cadastro:**
    - `config/fortify.php:147`;
    - `CreateNewUser:26-30`;
    - `pages/auth/register.tsx`;
    - `config/ebd.php:19` (`registration_enabled`, código morto).

---

## C1 — Programas (PR 1)

### Task C1.1: Entidade programa e migração da igreja atual

**Files:**

- Create:
    - `database/migrations/2026_10_22_000100_create_programs_table.php`;
    - `app/Models/Program.php`;
    - `database/factories/ProgramFactory.php`;
    - `tests/Feature/Migrations/CreateProgramsTest.php`;
    - `tests/Feature/ProgramModelTest.php`.
- Modify:
    - `app/Models/Classroom.php` (`program()`), `app/Models/Church.php` (`programs()`), `app/Models/User.php` (`coordinatedPrograms()`, `isCoordinatorOf(Program)`);
    - `database/factories/ClassroomFactory.php` (`program_id` padrão: o programa da igreja da turma, criando o programa "Escola Bíblica Dominical" se faltar);
    - `database/seeders/ClassroomSeeder.php`;
    - `app/Actions/Classrooms/CreateClassroom.php` (`handle(array $data, Program $program)`);
    - `app/Http/Controllers/Admin/ClassroomController.php` (escolha do programa; uma igreja com um programa só não mostra o campo).

**Interfaces:**

- **Schema:**
    - `programs(id, church_id FK cascade, name varchar(100), short_name varchar(30), slug varchar(120), position smallint default 0, timestamps, unique(church_id, slug))`;
    - `program_user(id, program_id FK cascade, user_id FK cascade, timestamps, unique(program_id, user_id))`, para coordenadores;
    - `classrooms.program_id` FK `restrictOnDelete`, NOT NULL depois do backfill.
- **Backfill** (mesmo padrão de `create_churches_table`): para cada igreja com turmas, um programa `name='Escola Bíblica Dominical'`, `short_name='EBD'`, `slug='ebd'`, com todas as turmas da igreja. Banco vazio não ganha programa.
- **Produces:**
    - `Program::classrooms()`, `Program::coordinators()`, `Program::church()`;
    - `Church::programs()`;
    - `Church::defaultProgram(): Program` (o primeiro; cria "Escola Bíblica Dominical" se não houver);
    - `Classroom::program()`;
    - `User::isCoordinatorOf(Program $program): bool`.

- [ ] **Step 1:** Testes que falham. Em `CreateProgramsTest`:
    - classes existentes ganham o programa EBD da própria igreja;
    - banco vazio fica sem programa;
    - o rollback desfaz tudo.

    Em `ProgramModelTest`:
    - a factory põe as turmas de uma igreja no mesmo programa;
    - o seeder cria o programa EBD;
    - `defaultProgram()` cria só uma vez.

- [ ] **Step 2:** Rodar e ver falhar.
- [ ] **Step 3:** Implementar.
- [ ] **Step 4:** Rodar os testes e a suíte inteira.
- [ ] **Step 5:** Testar `migrate` → `rollback` → `migrate` no `ebd_lab`.
- [ ] **Step 6:** Commit `feat: programas de ensino (EBD como primeiro programa)`.

### Task C1.2: Coordenador de programa

**Files:**

- Modify:
    - `app/Models/User.php` (`canManageClassroom`, `manageableClassroomIds`, `canAccessAdmin`);
    - a gestão de membros do programa: `app/Http/Controllers/Admin/ProgramCoordinatorController.php` (novo) e a rota em `routes/web.php`.
- Test: `tests/Feature/Admin/ProgramCoordinatorTest.php`.

**Interfaces:**

- `canManageClassroom($c)` = `isAdmin() || isTeacherOf($c) || isCoordinatorOf($c->program)`. O `isAdmin()` fica até a C2.
- `manageableClassroomIds()` inclui as turmas dos programas que a pessoa coordena.
- Coordenadores são nomeados por quem pode `assignTeachers`, com as mesmas regras de nomear professor.

- [ ] **Step 1:** Testes:
    - o coordenador do programa EBD vê e gerencia todas as turmas do programa (agenda, chamada, alunos, relatório), mas não as de outro programa da mesma igreja;
    - um professor não nomeia coordenador;
    - o admin nomeia.
- [ ] **Steps 2–5:** RED, implementar, GREEN, suíte, commit `feat: coordenador de programa`.

### Task C1.3: "EBD" vira o nome curto do programa nos textos

**Files:**

- Modify:
    - `app/Http/Resources/ClassroomResource.php` (`program_short_name`, `program_name`);
    - os pontos de texto da lista do mapa: `home.tsx:143,255,259,262`, `SendLessonReminder.php:50`, `MeetingStatus::Cancelled` (novo `label(?Program $program)`: "Sem {short_name}", ou seja, "Sem EBD" na igreja do Simon; sem programa conhecido, "Sem encontro"), `countdown.ts:14,18`, `my-week.tsx:224-225`, `whatsapp.ts:16,20`, `meetings/index.tsx`, `meetings/show.tsx`, `cancel-meeting-dialog.tsx`, `report.tsx:119`, `dashboard.tsx:74` / `settings/index.tsx:106` ("Gestão da EBD" → "Gestão"), `AnswerStudyQuestion.php:28` (prompt: só a troca de "Escola Bíblica Dominical (EBD)" pelo nome do programa, **sem** outra alteração no texto teológico).
- Test: um feature test com uma turma de um programa "Discipulado" (`short_name` "Discipulado") verifica o push da véspera ("Amanhã tem Discipulado!") e as props da página inicial. A igreja do Simon continua com "EBD" em tudo; os testes atuais não mudam.

- [ ] **Steps 1–5:** RED, implementar, GREEN, suíte, commit `feat: textos usam o nome do programa`.

### Task C1.4: Verificação e PR 1

- [ ] Checagens completas, laboratório (a igreja do Simon igual; uma turma num programa "Discipulado" mostra os textos certos), revisão final independente e PR. Merge com o CI verde e o OK do Simon.

---

## C2 — Organizações isoladas e cadastro (PR 2)

### Task C2.1: Autorização pela igreja

**Files:**

- Modify:
    - `app/Models/User.php:198-209,269-279` (`canManageClassroom`, `manageableClassroomIds`, `canAccessAdmin`);
    - `app/Policies/ClassroomPolicy.php` (`create(User, Church)`, `update` e `assignTeachers` = `isChurchAdminOf($classroom->church)`);
    - `StudentNotePolicy.php:16`, `UpdateStudentProfile.php:27`, `Admin/StudentController.php:168`, `Admin/ClassroomQuestionController.php:34` (o papel de igreja no lugar de `isAdmin`);
    - `Admin/ClassroomController.php:58` (a igreja do ator, não `primaryOrCreate`);
    - `Console/Commands/PromoteAdmins.php:40`.
- Test: `tests/Feature/Isolation/ChurchIsolationTest.php` (novo, a matriz do Review Focus 1).

**Interfaces:**

- `User::canManageClassroom(Classroom $c)` = `isTeacherOf($c) || isCoordinatorOf($c->program) || isChurchAdminOf($c->church)`.
- `User::manageableClassroomIds(): list<int>` **nunca** devolve `null`: devolve a união das turmas em que é professor, das dos programas que coordena e das das igrejas que administra.
- `User::currentChurch(): ?Church`: a igreja que administra ou, senão, a da primeira turma.
- `Church::displayName()` vira `Church::nameFor(?User $user): string`: a igreja da pessoa, senão a configuração.

- [ ] **Step 1:** `ChurchIsolationTest`, com duas igrejas montadas por `Classroom::factory()->for($churchB)` e um estado de factory `forChurch`. Para o admin, o professor e o coordenador da B contra a A:
    - toda rota de `/admin` de turma, encontro, lição, série, material, bloco, leitura, aluno, anotação, relatório e dúvidas → 403/404 sem efeito;
    - `GET /admin` e `/admin/classes` sem dados da A;
    - MCP `list_classrooms`, `get_lesson`, `save_lesson_draft` e `move_student` sem acesso à A.

    E também:
    - `church.name` para um usuário da B = o nome da B;
    - o e-mail de redefinição de senha usa o nome da igreja da pessoa.

- [ ] **Step 2:** RED.
- [ ] **Step 3:** Implementar.
- [ ] **Step 4:** Suíte inteira. `AdminAccessTest` e `Mcp/*` continuam verdes, com o Simon como admin da igreja dele.
- [ ] **Step 5:** Commit `feat: autorização pela igreja`.

### Task C2.2: Pessoas presas à igreja

**Files:**

- Modify:
    - `app/Actions/Classrooms/AddClassroomMember.php` (só acha quem já é membro de alguma turma que o ator gerencia; a mensagem é única, "Não encontramos essa pessoa nas suas turmas. Envie um convite pelo link de acesso.");
    - `app/Actions/Access/IssueAccessLink.php` (só emite se o emissor gerencia **todas** as turmas da pessoa);
    - `app/Actions/Classrooms/UpdateStudentProfile.php` (mesma regra);
    - `app/Actions/Classrooms/MoveStudentToClassroom.php` e `app/Mcp/Tools/MoveStudent.php` (`$from->church_id === $to->church_id`);
    - `app/Console/Commands/MergeAccounts.php` (exige a mesma igreja).
- Test: `tests/Feature/Isolation/PeopleIsolationTest.php` (Review Focus 2).

- [ ] **Steps 1–5:** RED, implementar, GREEN, suíte, commit `fix: pessoas não atravessam igrejas (fecha S-01)`.

### Task C2.3: Listas, biblioteca e início pela igreja

**Files:**

- Modify:
    - `app/Models/Lesson.php:249-266` (`visibleTo`: sem o bypass do admin; as públicas limitadas às igrejas do usuário, e ao visitante só pelo link direto);
    - `app/Support/ClassroomSelector.php:21`;
    - `LibraryController.php:26-31,41`;
    - `LibrarySearch.php:34-40` e `availableYears`;
    - `CurrentLessonQuery.php:89-90`.
- **Visitante anônimo:** `/biblioteca` e a página inicial mostram as turmas e lições públicas da **igreja do Simon** (primary) até existir o site institucional (etapa E). Ruling: é o comportamento de hoje para quem chega pelo WhatsApp, e nenhuma outra igreja aparece para ele.
- Test: `tests/Feature/Isolation/ListingIsolationTest.php` (Review Focus 3).

- [ ] **Steps 1–5:** RED, implementar, GREEN, suíte, commit `fix: listas e biblioteca por igreja`.

### Task C2.4: "Criar minha igreja"

**Files:**

- Create:
    - `app/Actions/Churches/CreateChurchWithAdmin.php`;
    - a página `resources/js/pages/auth/create-church.tsx` (no lugar de `auth/register.tsx`);
    - `tests/Feature/Auth/CreateChurchTest.php`.
- Modify:
    - `app/Actions/Fortify/CreateNewUser.php` (cria a pessoa, a igreja, o vínculo de administrador e o programa padrão numa transação);
    - `FortifyServiceProvider` (`registerView` → `auth/create-church`);
    - `config/fortify.php:147` (passa a ler `config('ebd.registration_enabled')`);
    - `resources/js/pages/auth/login.tsx:105` ("Criar minha igreja");
    - a rota `GET /criar-igreja` para quem já está logado;
    - o início do administrador novo sem turma: um passo a passo (criar a primeira turma, convidar um professor, cadastrar uma lição).

**Interfaces:**

- Campos: nome, e-mail, senha, nome da igreja e nome do programa (padrão "Escola Bíblica Dominical"; o nome curto vem preenchido como "EBD" ou derivado).
- `CreateChurchWithAdmin::handle(User $user, string $churchName, string $programName): Church`.

- [ ] **Step 1:** Testes (Review Focus 4):
    - o cadastro cria a pessoa, a igreja, o administrador e o programa;
    - e-mail duplicado e campos vazios dão erro de validação;
    - um logado cria uma igreja com o mesmo login;
    - o administrador novo cai no passo a passo;
    - a igreja nova não vê nada da do Simon (reaproveita os helpers de `ChurchIsolationTest`).
- [ ] **Steps 2–5:** RED, implementar, GREEN, suíte, commit `feat: criar minha igreja`.

### Task C2.5: Verificação e PR 2

- [ ] **Checagens:** testes completos, Larastan, Pint, `tsc`, `vp check` e build.
- [ ] **Laboratório:**
    - criar uma igreja nova pelo cadastro;
    - criar uma turma e um professor nela;
    - conferir com o Playwright que nada da igreja do Simon aparece;
    - conferir que a igreja do Simon continua igual para aluno, professor e o próprio Simon.
- [ ] **Revisão final** independente, com foco em isolamento e com a matriz em mãos.
- [ ] **PR e merge** com o CI verde e o OK do Simon.
- [ ] **Depois do deploy, só leitura:** contagem de igrejas e programas, e páginas respondendo 200.

## Fora desta etapa

- Fuso por igreja.
- Painel do dono (etapa D).
- Biblioteca oficial e cópias (etapa D).
- Site institucional (etapa E).
- Os textos "domingo" que ficaram como minor na entrega 2.
