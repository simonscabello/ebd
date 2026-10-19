# Entrega 1 — Igreja como entidade (migração invisível) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Criar a entidade "igreja", pôr todas as classes atuais dentro da igreja do Simon e registrar os administradores da igreja, sem nenhuma mudança visível para aluno, professor ou admin.

**Architecture:**

- Tabela `churches`, pivô `church_user` (administradores da igreja) e coluna `classrooms.church_id`.
- Uma única migration cria tudo e faz o backfill a partir de `config/ebd.php` (`church_name`, `timezone`).
- O nome exibido no app passa a vir da igreja no banco, com o mesmo valor de antes.
- O fuso, a agenda e a IA continuam como hoje: são as entregas 2 e 4.

**Tech Stack:** Laravel 13, PHP 8.4, PostgreSQL, PHPUnit 12, Inertia 3/React 19.

**Spec:** `docs/superpowers/specs/2026-10-19-plataforma-multi-igreja-design.md` (seções "Modelagem decidida" e "Migração da igreja atual").

## Global Constraints

- Na igreja do Simon, nada muda para aluno e professor: os mesmos links pessoais, sem ninguém precisar entrar de novo, sem perder presença, anotação ou progresso.
- O merge na `main` publica direto em produção (Railway). A migration precisa rodar com o app no ar e não perder dados.
- PRs sempre com `--base main`.
- Testes nunca usam rede (`Http::preventStrayRequests` já está no `TestCase`).
- Slugs continuam globais. Esta entrega não muda nenhuma URL.
- Dono do sistema: só o Simon (`users.is_admin`; em produção só existe 1 conta com esse valor, conferido em 2026-10-19).
- Código e comentários seguem o estilo do repositório: português nos comentários e nas mensagens, Actions e Queries, `vp check --fix` antes do push.

## Review Focus

1. **Produção com config em cache:** a migration lê `config('ebd.church_name')` e `config('ebd.timezone')` na hora do deploy. Se a variável não existir em produção, a igreja nasceria "Nossa Igreja". Conferir o valor em produção antes do merge (Task 4, passo 1).
2. **Nome com acento ou só símbolos:** o slug é gerado a partir do nome. "Segunda Igreja Batista em Barcelona" vira `segunda-igreja-batista-em-barcelona`; um nome que gera slug vazio vira `igreja`. Teste na Task 1.
3. **Banco vazio** (testes, `make setup`): a migration não cria igreja quando não há classes nem admins, e o seeder cria a igreja antes das classes. Testes nas Tasks 1 e 2.
4. **Rollback:** o `down()` remove a coluna e as tabelas sem erro, inclusive depois do backfill. Teste na Task 1.
5. **Admin sem classe:** um `is_admin` que não é professor de nenhuma classe também vira administrador da igreja. Teste na Task 1.

---

## Preparação

- [ ] Criar a branch a partir da `main` atualizada: `git switch main && git pull && git switch -c feat/igreja-base`.
- [ ] Commitar o spec e este plano: `git add docs/superpowers && git commit -m "docs: design da plataforma multi-igreja e plano da entrega 1"`. A mensagem termina com a linha `Co-Authored-By` da sessão.

### Task 1: Migration `churches` + backfill da igreja atual

**Files:**

- Create: `database/migrations/2026_10_20_000100_create_churches_table.php`
- Test: `tests/Feature/Migrations/CreateChurchesTest.php` (mesmo padrão de `tests/Feature/Migrations/BackfillMeetingsTest.php`: `migrate:rollback --path`, inserir com `DB::table`, `migrate --path`)

**Interfaces:**

- Produces, schema:
    - `churches(id, name varchar(120), slug varchar(140) unique, timezone varchar(64), timestamps)`;
    - `church_user(id, church_id FK cascadeOnDelete, user_id FK cascadeOnDelete, timestamps, unique(church_id, user_id), index(user_id))`;
    - `classrooms.church_id` FK `restrictOnDelete`, **NOT NULL**, com índice.

- [ ] **Step 1: Escrever os testes que falham.** `CreateChurchesTest` com `const MIGRATION = 'database/migrations/2026_10_20_000100_create_churches_table.php'`:
    - `test_existing_classrooms_and_admins_move_into_the_current_church`:
        - `config(['ebd.church_name' => 'Segunda Igreja Batista em Barcelona', 'ebd.timezone' => 'America/Sao_Paulo'])`.
        - Fazer o rollback.
        - Inserir as classes `jovens` e `adultos`, um usuário `is_admin = true` que não está em nenhuma classe, e um aluno.
        - Migrar de novo e conferir:
            - `DB::table('churches')->count() === 1`;
            - `name === 'Segunda Igreja Batista em Barcelona'`, `slug === 'segunda-igreja-batista-em-barcelona'`, `timezone === 'America/Sao_Paulo'`;
            - as duas classes com `church_id` dessa igreja;
            - `church_user` contém só o admin;
            - `Schema::hasColumn('classrooms', 'church_id')`, e um insert em `classrooms` sem `church_id` lança `QueryException` (NOT NULL).
    - `test_an_empty_database_gets_no_church`: rollback e migrate sem dados → `churches` vazia.
    - `test_a_name_without_letters_gets_a_fallback_slug`: com `ebd.church_name = '***'` e uma classe → `slug === 'igreja'`.
    - `test_rollback_after_backfill_restores_the_old_schema`: rollback → `Schema::hasTable('churches') === false` e `Schema::hasColumn('classrooms', 'church_id') === false`. As classes continuam lá.

- [ ] **Step 2: Rodar e ver falhar.**
    - Comando: `DB_PORT=5432 php artisan test --filter=CreateChurchesTest`.
    - Esperado: FAIL, porque o arquivo da migration não existe.

- [ ] **Step 3: Implementar a migration.**
    - `up()` cria as duas tabelas e adiciona `classrooms.church_id` como nullable.
    - Backfill, **só se** houver classes ou usuários `is_admin`:
        - inserir uma igreja com `name = config('ebd.church_name')`, `slug = Str::slug(name) ?: 'igreja'` e `timezone = config('ebd.timezone')`;
        - `update classrooms set church_id = <id>`;
        - inserir em `church_user` todos os `is_admin = true`.
    - Depois do backfill: `church_id` vira NOT NULL e ganha o índice.
    - `down()`: remove a FK e a coluna, depois `church_user` e `churches`.
    - Comentário no topo explicando que a igreja atual nasce da configuração e que, nesta entrega, nada mais lê dela além do nome.

- [ ] **Step 4: Rodar e ver passar.** Mesmo comando; esperado PASS (4 testes).

- [ ] **Step 5: Conferir no banco do laboratório.** `DB_PORT=5432 DB_DATABASE=ebd_lab php artisan migrate`, depois `migrate:rollback --step=1`, depois `migrate`. Esperado: `DONE` nas três vezes e `select count(*) from classrooms where church_id is null` = 0.

- [ ] **Step 6: Commit.**
    ```bash
    git add database/migrations/2026_10_20_000100_create_churches_table.php tests/Feature/Migrations/CreateChurchesTest.php
    git commit -m "feat: igreja como entidade, com as classes atuais migradas para ela"
    ```

### Task 2: Models, factories e seeder

**Files:**

- Create: `app/Models/Church.php`, `database/factories/ChurchFactory.php`
- Modify:
    - `app/Models/Classroom.php` (relação e `@property int $church_id`);
    - `app/Models/User.php` (relações e checagem de admin da igreja);
    - `database/factories/ClassroomFactory.php`;
    - `database/seeders/ClassroomSeeder.php`.
- Test: `tests/Feature/ChurchModelTest.php`

**Interfaces:**

- Consumes: o schema da Task 1.
- Produces:
    - `Church::classrooms(): HasMany<Classroom>`
    - `Church::admins(): BelongsToMany<User>` (pivô `church_user`, `withTimestamps()`)
    - `Church::primary(): ?Church`: a igreja mais antiga (menor `id`), sem criar nada. Uso temporário até a entrega 3, quando a igreja passa a vir de quem está logado ou da classe.
    - `Church::primaryOrCreate(): Church`: igual, mas cria a partir de `config('ebd.church_name')` e `config('ebd.timezone')` (mesma regra de slug da Task 1) quando não existe nenhuma. Só para caminhos de escrita.
    - `Classroom::church(): BelongsTo<Church>`
    - `User::administeredChurches(): BelongsToMany<Church>`
    - `User::isChurchAdminOf(Church $church): bool`
    - `ChurchFactory` (nome, slug único, `timezone = 'America/Sao_Paulo'`)
    - `ClassroomFactory`: `church_id` padrão = a igreja já existente (`Church::primary()`) ou uma nova pela factory. As classes de um teste ficam na mesma igreja, como em produção.

- [ ] **Step 1: Escrever os testes que falham** em `ChurchModelTest`:
    - `test_classrooms_from_the_factory_share_one_church`: duas `Classroom::factory()->create()` → mesmo `church_id`, `Church::query()->count() === 1`.
    - `test_church_admin_check`: igreja com um admin anexado → `isChurchAdminOf` true para ele e false para outro usuário; `administeredChurches` devolve a igreja.
    - `test_primary_or_create_uses_the_configured_name_only_when_empty`: com `config(['ebd.church_name' => 'Igreja Teste'])` e banco sem igreja → `primaryOrCreate()->name === 'Igreja Teste'`; uma segunda chamada não cria outra (`count === 1`); `primary()` com banco vazio devolve `null`.
    - `test_seeder_puts_seeded_classrooms_in_a_church`: `$this->seed(ClassroomSeeder::class)` → as classes `jovens` e `adultos` com `church_id` não nulo e da mesma igreja.

- [ ] **Step 2: Rodar e ver falhar.** `DB_PORT=5432 php artisan test --filter=ChurchModelTest`. Esperado: FAIL, porque a classe `App\Models\Church` não existe.

- [ ] **Step 3: Implementar.**
    - `church_id` **não** entra no `#[Fillable]` de `Classroom`; ele é sempre atribuído explicitamente.
    - `ClassroomSeeder` chama `Church::primaryOrCreate()` e preenche `church_id` no `updateOrCreate`.
    - Anotar as relações no estilo dos models existentes (`@return HasMany<Classroom, $this>`).

- [ ] **Step 4: Rodar e ver passar**, e depois a suíte inteira: `DB_PORT=5432 php artisan test`. Esperado: tudo verde, porque as factories passam a criar a igreja sozinhas.

- [ ] **Step 5: Commit.** `git commit -m "feat: models e factories da igreja"`.

### Task 3: Classe nova entra na igreja; nome exibido vem do banco

**Files:**

- Modify:
    - `app/Actions/Classrooms/CreateClassroom.php`;
    - `app/Http/Controllers/Admin/ClassroomController.php:55-62`;
    - `app/Http/Middleware/HandleInertiaRequests.php:47-49`;
    - `app/Notifications/ResetPasswordNotification.php:32`.
- Test:
    - `tests/Feature/Admin/AdminAccessTest.php` (onde já está o teste de `post('/admin/classes')`);
    - `tests/Feature/ChurchModelTest.php`.

**Interfaces:**

- Consumes: `Church::primary()`, `Church::primaryOrCreate()` (Task 2).
- Produces: `CreateClassroom::handle(array $data, Church $church): Classroom`.

- [ ] **Step 1: Escrever os testes que falham.**
    - `test_a_new_classroom_joins_the_church`: o admin cria uma classe pela rota existente → `church_id === Church::primary()->id`.
    - `test_the_church_name_shown_comes_from_the_church_record`: renomear a igreja para "Igreja do Teste" → `get('/login')` com `assertInertia(fn ($p) => $p->where('church.name', 'Igreja do Teste'))`.
    - `test_the_configured_name_is_the_fallback_without_a_church`: sem igreja, `church.name === config('ebd.church_name')`.

- [ ] **Step 2: Rodar e ver falhar.**

- [ ] **Step 3: Implementar.**
    - `ClassroomController::store` passa `Church::primaryOrCreate()` para `CreateClassroom::handle()`.
    - `HandleInertiaRequests` e `ResetPasswordNotification` usam `Church::primary()?->name ?? config('ebd.church_name')`. Só leitura: nenhuma escrita em GET.

- [ ] **Step 4: Rodar os testes e depois a suíte inteira.** Esperado: tudo verde.

- [ ] **Step 5: Commit.** `git commit -m "feat: classe nova entra na igreja e o nome exibido vem do banco"`.

### Task 4: Verificação e publicação

**Files:** nenhum novo.

- [ ] **Step 1: Conferir em produção (só leitura) o valor que a migration vai usar.**
    ```bash
    railway ssh -s app -e production -- php artisan tinker --execute="echo config('ebd.church_name').' | '.config('ebd.timezone').' | admins='.App\Models\User::where('is_admin',true)->count().PHP_EOL;"
    ```
    Esperado: `Segunda Igreja Batista em Barcelona | America/Sao_Paulo | admins=1`. Se o nome vier "Nossa Igreja", **parar** e ajustar `EBD_CHURCH_NAME` no Railway com o Simon antes do merge.
- [ ] **Step 2: Rodar as checagens locais.** `DB_PORT=5432 php artisan test`, `vendor/bin/pint --test`, `vendor/bin/phpstan analyse`, `npx tsc --noEmit`, `npx vp check --fix` e `npm run build`. Esperado: tudo verde.
- [ ] **Step 3: Ver que nada mudou no navegador.** Com o servidor do laboratório (`ebd_lab`, porta 8011) e o Playwright no scratchpad, entrar como `aluno@ebd.test` e como `professor@ebd.test`. Abrir o início, a lição da semana e a gestão da classe. Esperado: as mesmas telas, com o nome da igreja no topo.
- [ ] **Step 4: Abrir o PR.** `gh pr create --base main`. A descrição diz que a mudança é invisível e traz o resultado do passo 1. Esperar o CI ficar verde e o merge do Simon.
- [ ] **Step 5: Conferir depois do deploy (só leitura).**
    - Log do deploy no Railway mostra a migration `DONE`.
    - `churches` com 1 linha; `classrooms where church_id is null` = 0; `church_user` com 1 linha.
    - Página `/` responde 200 e mostra o mesmo nome no topo.

## Próximas entregas (cada uma com o próprio plano, escrito depois que a anterior estiver no ar)

2. **Agenda por classe:**
    - dia e horário na classe; fuso na igreja;
    - "hoje", semana de leitura, lembretes e `FillSundays` passam a seguir a classe;
    - "Modo Domingo" vira "Modo Encontro" fora do domingo.
3. **Administrador da igreja e cadastro:**
    - "Criar minha igreja", fim do cadastro só de usuário;
    - painel da igreja e isolamento entre igrejas, com a matriz de testes de autorização: telas, biblioteca, relatórios, lembretes, aba Dúvidas, MCP e IA.
4. **Acervos e IA por igreja:**
    - acervo do dono (a série _Fora de Contexto_ vai para lá) e acervo da igreja;
    - amostra, pedido de acesso e aprovação;
    - cópia editável com "nova versão";
    - IA e áudio liberados e limitados por igreja;
    - texto bíblico só na igreja do Simon (`/biblia` com o contexto da lição).
5. **Site institucional:** apresentação, capturas, estudos de amostra, como funciona, benefícios.
6. **Marca centralizada:** nome, ícone e cores num lugar só.
