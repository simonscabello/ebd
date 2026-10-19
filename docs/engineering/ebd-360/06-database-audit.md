# 06 · Auditoria de banco de dados

Auditoria EBD 360 · papel: Database Engineer · data: 2026-10-19 · branch `engineering/ebd-360`

Escopo: modelo reconstruído a partir de `database/migrations` e `app/Models`; queries reais em `app/Queries`, `app/Actions`, `app/Http/Controllers` e `app/Mcp`. Só leitura. O banco de desenvolvimento (`ebd-postgres-1`) foi consultado só com `SELECT`/`EXPLAIN`. Ele está atrasado: a última migration aplicada é `2026_10_05_000100`, então `study_questions` não existe lá. Também é pequeno: 12 lições, 8 usuários, 31.105 versículos e 14 presenças. Por isso os planos abaixo servem para dizer **se um índice pode ser usado**, não quanto custa uma consulta em produção.

Legenda: **Confirmado** = bug ou comportamento que o código e o banco provam. **Risco** = dá para demonstrar, mas depende de concorrência, volume ou uso. **Hipótese** = precisa ser verificada. **Preferência** = estilo ou robustez sem defeito atual. Severidade vai de P0 (crítico) a P3 (baixo).

---

## 1. Modelo (ER resumido)

```
users ─┬─< classroom_user >─ classrooms ─┬─< series ─< lessons (soft delete)
       │   (role teacher|student,        │              ├─< lesson_blocks   (audience teacher|student, drip_weekday)
       │    unique classroom+user)       │              ├─< lesson_readings (weekday 1..7)
       │                                 │              ├─< lesson_materials(audience, disk/path|url)
       │                                 │              ├─< lesson_authors >─ users
       │                                 │              └─  search_vector (tsvector gerado) + blocks_text
       │                                 ├─< class_meetings (unique classroom+held_on; lesson_id restrict)
       │                                 │        └─< attendances >─ users (unique meeting+user)
       │                                 ├─< student_notes (aluno, autor)
       │                                 └─< access_links (classroom_id null on delete)
       ├─< reading_checkins (lesson, lesson_reading?, weekday, read_on; unique user+lesson+weekday)
       ├─< lesson_notes     (unique user+lesson)
       ├─< study_questions  (perguntas da IA; sem unique)
       ├─< user_badges      (unique user+badge+coalesce(series,0))
       ├─< push_subscriptions (endpoint unique)
       ├─< access_links     (token_hash unique; 1 ativo por usuário, índice parcial)
       └─< audit_logs       (MCP; podado por model:prune)
bible_verses (book, chapter, verse) unique · oauth_* (Passport) · sessions/cache/jobs (padrão Laravel)
```

Não existe tabela `notifications`. O push é enviado direto às `push_subscriptions` e não fica registrado. Papel de professor e de aluno é por classe (`classroom_user.role`). Admin é global (`users.is_admin`).

## 2. Constraints e índices

| Tabela                               | Unique / check                                                                                                                                | Índices                                                                               | FKs e onDelete                                       | Observação                                                     |
| ------------------------------------ | --------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------- | ---------------------------------------------------- | -------------------------------------------------------------- |
| classrooms                           | slug                                                                                                                                          | —                                                                                     | —                                                    | ok                                                             |
| classroom_user                       | (classroom_id, user_id); check role                                                                                                           | (user_id, role)                                                                       | cascade / cascade                                    | ok                                                             |
| series                               | (classroom_id, slug)                                                                                                                          | —                                                                                     | classroom restrict                                   | ok                                                             |
| lessons                              | slug **global** (inclui excluídas); `(series_id, number)` parcial `WHERE deleted_at IS NULL AND number IS NOT NULL`; checks status/visibility | (classroom_id, status), (series_id, scheduled_for), scheduled_for, GIN(search_vector) | classroom restrict, series restrict, created_by null | `created_by` sem índice                                        |
| lesson_blocks / readings / materials | checks de kind/audience/weekday/source                                                                                                        | (lesson_id, position)                                                                 | lesson cascade                                       | ok                                                             |
| class_meetings                       | **(classroom_id, held_on)**; check status                                                                                                     | lesson_id                                                                             | classroom cascade, lesson **restrict**               | impede dois encontros no mesmo domingo ✓                       |
| attendances                          | **(class_meeting_id, user_id)**                                                                                                               | user_id                                                                               | meeting cascade, user cascade, recorded_by null      | presença duplicada impossível ✓; `recorded_by` sem índice      |
| reading_checkins                     | **(user_id, lesson_id, weekday)**; check weekday                                                                                              | (user_id, read_on), (lesson_id, read_on)                                              | user/lesson cascade, reading null                    | leitura duplicada impossível ✓; `lesson_reading_id` sem índice |
| lesson_notes                         | (user_id, lesson_id)                                                                                                                          | —                                                                                     | cascade                                              | `lesson_id` sem índice próprio                                 |
| study_questions                      | —                                                                                                                                             | (user_id, created_at), (lesson_id, created_at)                                        | cascade                                              | não tem como impor o limite diário no banco (ver D-3)          |
| user_badges                          | (user_id, badge, COALESCE(series_id,0))                                                                                                       | user_id                                                                               | cascade                                              | `series_id` sem índice                                         |
| push_subscriptions                   | endpoint                                                                                                                                      | —                                                                                     | user cascade                                         | **`user_id` sem índice**                                       |
| access_links                         | token_hash; 1 ativo por user (parcial)                                                                                                        | user_id                                                                               | user cascade, classroom/created_by null              | `classroom_id` e `created_by` sem índice                       |
| student_notes                        | —                                                                                                                                             | (classroom_id, user_id, created_at)                                                   | cascade / author null                                | `user_id` e `author_id` sem índice próprio                     |
| audit_logs                           | —                                                                                                                                             | (user_id, created_at), (classroom_id, created_at)                                     | null                                                 | podado diariamente (`routes/console.php:39`)                   |
| bible_verses                         | (book, chapter, verse); check book                                                                                                            | —                                                                                     | —                                                    | a consulta de trecho usa o índice (EXPLAIN: 0,6 ms)            |

FKs sem índice na primeira coluna. Postgres não cria esse índice sozinho. Consulta feita no banco de desenvolvimento:

```sql
SELECT c.conrelid::regclass, a.attname, c.confrelid::regclass, c.confdeltype
FROM pg_constraint c JOIN pg_attribute a ON a.attrelid=c.conrelid AND a.attnum=c.conkey[1]
WHERE c.contype='f' AND NOT EXISTS (SELECT 1 FROM pg_index i WHERE i.indrelid=c.conrelid AND i.indkey[0]=c.conkey[1]);
```

Resultado: `lessons.created_by`, `access_links.classroom_id`, `access_links.created_by`, `reading_checkins.lesson_reading_id`, `lesson_notes.lesson_id` (cascade), `user_badges.series_id` (cascade), `attendances.recorded_by`, `push_subscriptions.user_id` (cascade), `student_notes.user_id` (cascade) e `student_notes.author_id`.

Pontos que estão bem resolvidos: `insertOrIgnore` sobre chaves únicas na presença (`RecordAttendance.php:68`), no check-in (`RecordReadingCheckin.php:39`), nos selos (`AwardBadges.php:67`) e nos domingos (`FillSundays.php:59`). Claim atômico do áudio por `UPDATE` condicional (`RequestLessonAudio.php:45-58`). `lockForUpdate` em `ShiftPlannedLessons.php:27`. Transações nas operações com várias tabelas (CreateLesson, UpdateLesson, SaveMeeting, PlanMeetings, CancelMeeting, MoveStudentToClassroom, IssueAccessLink, MergeAccounts). `Model::preventLazyLoading` ligado fora de produção (`AppServiceProvider.php:75`), então N+1 aparece nos testes.

## 3. Achados

### D-1 · Risco · P3 · confiança alta: `push_subscriptions.user_id` sem índice

`2026_09_24_000300_create_push_subscriptions_table.php:16` usa `foreignId()->constrained()` sem índice. Toda notificação passa por `SendReadingReminders::subscribedMembers` (`SendReadingReminders.php:110-116`, com `whereHas('pushSubscriptions')` e `with('pushSubscriptions')`). Esse método roda na publicação (`NotifyLessonPublished.php:23`) e duas vezes por dia por classe. Com `enable_seqscan=off`, o plano continua em `Seq Scan ... Disabled: true`, ou seja, não existe índice que o Postgres possa usar. Hoje a tabela é pequena e o impacto é baixo, mas é a FK com cascade mais consultada.
**Correção, baixo risco:** `CREATE INDEX CONCURRENTLY push_subscriptions_user_id_index ON push_subscriptions (user_id)`. Não precisa limpar dados.

### D-2 · Preferência · P3 · confiança alta: outras FKs sem índice

As FKs listadas na seção 2 só pesam em `DELETE` de usuário, lição ou série, porque cada cascade ou `SET NULL` varre a tabela filha. Isso é raro (exclusão de conta, `MergeAccounts`). Vale indexar `student_notes.user_id`, `lesson_notes.lesson_id` e `attendances.recorded_by`, que crescem com o uso. Não precisa limpar dados.

### D-3 · Risco · P3 · confiança alta: o limite diário da IA pode ser furado com pedidos simultâneos

`AnswerStudyQuestion.php:79` confere `remainingToday()`. Depois a requisição espera a OpenAI (`:83-88`, até `EBD_HELPER_TIMEOUT=60` s) e só então grava (`:96`). Sem lock, N pedidos paralelos passam pela checagem com o mesmo saldo. O throttle `study-helper` (5/min, `AppServiceProvider.php:124`) limita o estouro a no máximo 4 perguntas além do limite por minuto. Impacto: custo de API, não integridade.
**Correção, baixo risco:** `Cache::lock("study-helper:{$user->id}", 90)->block(0)` em volta do handle, ou reservar a linha antes (inserir com `answer` nulo e preencher depois, o que exige mudar a coluna para nullable). Não precisa limpar dados.

### D-4 · Risco · P3 · confiança média: chamada simultânea vira união, e um pedido antigo pode sobrescrever um novo

`RecordAttendance.php:42-53` lê o roster e o `$before` **fora** da transação. Dentro dela (`:55-74`) faz `DELETE ... whereNotIn(present)` e depois `insertOrIgnore`. Com duas pessoas fazendo a chamada ao mesmo tempo em READ COMMITTED, o `DELETE` de uma não enxerga o insert ainda não confirmado da outra, e o resultado é a **união** das duas listas, não a última. Além disso, o front salva a lista inteira com debounce de 800 ms (`attendance-sheet.tsx:49-63`). O Inertia cancela a visita anterior no cliente, mas o servidor continua processando. Se o pedido antigo chegar depois do novo (Wi-Fi ruim), ele sobrescreve a marcação mais recente. A unique `(class_meeting_id, user_id)` garante que não há duplicata.
**Correção, baixo risco:** `ClassMeeting::lockForUpdate()` na linha do encontro no início da transação (serializa as chamadas) e um número de versão ou `updated_at` enviado pelo cliente para descartar pedidos antigos. Não precisa limpar dados.

### D-5 · Risco · P3 · confiança alta: `updateOrCreate` sobre chave única pode virar 500

`LessonNoteController.php:30` (unique `user_id+lesson_id`) e `PushSubscriptionController.php:82` (unique `endpoint`): dois pedidos simultâneos fazem o SELECT e os dois tentam INSERT, e um deles quebra com `UniqueConstraintViolationException`. Acontece com duplo toque ou com um `pushsubscriptionchange` disparado junto com uma inscrição.
**Correção:** `upsert()` ou `createOrFirst()` / `updateOrCreate` envolvido em retry. Mesmo caso: `GenerateLessonSlug` confere e depois insere (`GenerateLessonSlug.php:44-47`), e lições simultâneas com o mesmo título dão 500. Não precisa limpar dados.

### D-6 · Risco · P3 · confiança alta: a busca com o título da série não usa o índice GIN

`LibrarySearch.php:46-51` faz `lessons.search_vector @@ q OR to_tsvector(coalesce(series.title,'')) @@ q`. O `OR` com uma expressão calculada na tabela do `LEFT JOIN` obriga a varrer todas as lições visíveis. EXPLAIN com `enable_seqscan=off`: sem o OR o plano usa `Bitmap Index Scan on lessons_search_vector_index`; com o OR vira `Nested Loop Left Join` + `Filter`. Com o acervo atual (dezenas a centenas de lições) isso não pesa.
**Correção, quando o acervo crescer:** incluir o título da série em `blocks_text` (já sincronizado por `SyncLessonSearchText`) ou trocar o OR por `UNION`. Não precisa limpar dados.

### D-7 · Preferência · P3: integridade entre classes só no app

`class_meetings.lesson_id` e `lessons.series_id` podem apontar para lição ou série de **outra** classe. O banco não impede. Quem impede é o app (`SaveMeeting.php:28-33`, `MeetingValidationRules.php:28,46`). Daria para usar FK composta `(lesson_id, classroom_id) → lessons(id, classroom_id)`, o que exige criar uma unique `(id, classroom_id)` em `lessons`. **Antes**, rodar `SELECT ... FROM class_meetings m JOIN lessons l ON l.id=m.lesson_id WHERE l.classroom_id<>m.classroom_id`. Se voltar alguma linha, ela precisa ser limpa.

### D-8 · Confirmado · P3 · confiança alta: tag do lembrete ignora o `$today` recebido

`SendReadingReminders.php:157,167` monta a tag com `ChurchCalendar::today()` e não com o `$today` passado para `handle()` (`:38-40`). Em produção os dois coincidem. Em testes ou num reenvio com outra data, a tag sai com o dia errado e o celular pode substituir o aviso errado. É uma linha para corrigir.

### D-9 · Risco · P3 · confiança alta: tela Dúvidas carrega todas as perguntas da classe

`ClassroomQuestionController.php:31-37` faz `->get()` em **todas** as `study_questions` da classe, com `question` e `answer` em texto, e só depois fica com 10 lições (`:39`). O total cresce sem teto: até 10 por aluno por dia. **Correção, baixo risco:** buscar primeiro os 10 `lesson_id` mais recentes (`GROUP BY lesson_id ORDER BY max(created_at) DESC LIMIT 10`) e carregar só as perguntas dessas lições. O índice `(lesson_id, created_at)` já existe.

### D-10 · Busca textual: correta para o tamanho atual

O tsvector é gerado com a configuração `ebd_portuguese` (unaccent + stem) e tem índice GIN (`2026_09_23_000600...:56-58`). A tsquery é saneada (`LibrarySearch.php:73-88`) e o headline é escapado (`:94-111`). A busca da gestão e do MCP usa `unaccent(title) ILIKE` sem índice (`Admin/LessonController.php:60`, `Mcp/Tools/ListLessons.php:53`). Isso é aceitável com centenas de lições. Com milhares, usaria `pg_trgm` com índice de expressão sobre `unaccent` (exige wrapper IMMUTABLE).

### D-11 · Fuso horário: consistente

Servidor e banco em UTC (`config/app.php:68`). "Hoje" vem de `ChurchCalendar` (`EBD_TIMEZONE`, `config/ebd.php:14`). As datas `held_on`/`read_on` são comparadas como texto `Y-m-d` (`ChurchCalendar.php:28-35`). O limite da IA usa `today()->utc()` (`AnswerStudyQuestion.php:64`). A data de entrada converte `created_at` com `AT TIME ZONE` (`ChurchCalendar.php:53`). Não achei `now()->toDateString()` nem `today()` sem fuso em `app/`. O agendador usa `->timezone($timezone)`. `whereDate()` sobre coluna `date` continua usando o índice (EXPLAIN: `Index Scan using class_meetings_classroom_id_held_on_unique`).

### D-12 · Hipótese · P3: `.env.example` aponta para Redis, mas a imagem não tem cliente Redis

`.env.example:57-62` define `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis` e `REDIS_CLIENT=phpredis`. O `Dockerfile:3` não instala `redis` e o `composer.json` não tem `predis`. Segundo a memória do projeto, produção usa `QUEUE_CONNECTION=sync` (não verifiquei as variáveis do Railway). Quem copiar o `.env.example` para produção derruba o app. Sessões, cache, rate limiter e `onOneServer` usam o banco.

## 4. Correções de baixo risco (resumo)

| #   | Correção                                                                                   | Migração segura?                                                 | Limpar dados antes?                           |
| --- | ------------------------------------------------------------------------------------------ | ---------------------------------------------------------------- | --------------------------------------------- |
| D-1 | índice `push_subscriptions(user_id)`                                                       | sim, `CONCURRENTLY` (migration com `$withinTransaction = false`) | não                                           |
| D-2 | índices em `student_notes(user_id)`, `lesson_notes(lesson_id)`, `attendances(recorded_by)` | sim                                                              | não                                           |
| D-3 | lock por usuário no "Tirar dúvida"                                                         | só código                                                        | não                                           |
| D-4 | `lockForUpdate` no encontro dentro de `RecordAttendance`                                   | só código                                                        | não                                           |
| D-5 | `upsert`/`createOrFirst` em notas e push                                                   | só código                                                        | não                                           |
| D-8 | usar `$today` na tag                                                                       | só código                                                        | não                                           |
| D-9 | limitar a consulta da tela Dúvidas                                                         | só código                                                        | não                                           |
| D-7 | FK composta classe↔lição                                                                   | exige unique `(id, classroom_id)`                                | **sim: conferir antes** com a consulta do D-7 |

Não encontrei presença duplicada, leitura duplicada nem dois encontros no mesmo domingo como bug: as três situações já são barradas por unique.
