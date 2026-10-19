# 04 — Auditoria técnica (Laravel)

> Escopo: leitura estática de `app/`, `routes/`, `bootstrap/app.php`, `database/migrations/` e trechos de `vendor/minishlink/web-push`.
> Nada foi executado contra o banco (a suíte estava rodando em outro processo). Data: 2026-10-19.
> Cada achado traz o caminho completo de entrada → resultado errado. Quando o caminho não pôde ser fechado só pela leitura, o achado foi rebaixado para hipótese (C).

## Legenda

| Classe | Significado                                                                                                                             |
| ------ | --------------------------------------------------------------------------------------------------------------------------------------- |
| **A**  | Bug confirmado pela leitura do caminho completo (entrada → resultado errado). Não executado.                                            |
| **B**  | Risco demonstrável: o código permite o estado errado, mas ele depende de concorrência, de entrada atípica ou de uma decisão de produto. |
| **C**  | Hipótese: suspeita plausível sem caminho fechado, ou que depende de comportamento que não deu para confirmar.                           |
| **D**  | Preferência ou higiene: código morto, duplicação, inconsistência de estilo. Não é defeito.                                              |

Severidade: P0 (dados ou segurança em produção, agora) → P3 (incômodo raro).
Custo: P = até 1h, M = meio dia, G = mais de um dia.

## Resumo

| #     | Classe | Sev. | Achado                                                                                                                                                | Evidência principal                                                                                                                                        |
| ----- | ------ | ---- | ----------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------- |
| A1    | A      | P2   | Uma inscrição push com chave inválida derruba o lote inteiro e os lembretes das classes seguintes                                                     | `app/Http/Controllers/PushSubscriptionController.php:23`, `app/Support/Push/WebPushSender.php:55`, `app/Actions/Notifications/SendReadingReminders.php:43` |
| A2    | A      | P3   | Numa lição que continua na semana seguinte, o lembrete das 20h diz "Você já leu hoje" para quem leu na semana anterior                                | `app/Actions/Notifications/SendReadingReminders.php:76-82`, migração `2026_09_23_001500…:40`                                                               |
| A3    | A      | P3   | "Encerrar aula" com "continua" num encontro sem lição grava o encerramento e depois devolve erro (escrita parcial; no MCP, sem registro no histórico) | `app/Actions/Meetings/FinishMeeting.php:28-34`, `ContinueLessonNextMeeting.php:31`                                                                         |
| A4    | A      | P3   | O professor troca o e-mail do aluno com outra caixa de letras e recebe erro 500 em vez da mensagem de validação                                       | `app/Http/Controllers/Admin/StudentController.php:182-187`                                                                                                 |
| A5    | A      | P3   | Um telefone com 21 a 30 dígitos passa na validação e estoura a coluna `varchar(20)` (erro 500)                                                        | `app/Concerns/StudentValidationRules.php:32`, migração `2026_09_23_001100…:18`                                                                             |
| B1    | B      | P2   | Na 2ª semana de uma lição que continua, o aluno não consegue registrar a leitura do dia: a sequência de dias quebra                                   | `app/Actions/Engagement/RecordReadingCheckin.php:39-44`                                                                                                    |
| B2    | B      | P3   | Duas chamadas salvas ao mesmo tempo produzem a união das listas, que não é nenhuma das duas                                                           | `app/Actions/Meetings/RecordAttendance.php:42-68`                                                                                                          |
| B3    | B      | P3   | Nenhuma data de encontro precisa ser domingo (formulário, criação de lição, MCP)                                                                      | `app/Concerns/MeetingValidationRules.php:22`, `app/Http/Requests/Admin/LessonRequest.php:57`                                                               |
| B4    | B      | P3   | "Teve aula" no servidor aceita encontro cancelado (a trava existe só na tela)                                                                         | `app/Http/Controllers/Admin/MeetingStatusController.php:51-61`                                                                                             |
| B5    | B      | P3   | "Sem EBD" e chamada concorrentes: o cancelamento confere a chamada fora da transação e sem trava                                                      | `app/Actions/Meetings/CancelMeeting.php:26-32`                                                                                                             |
| B6    | B      | P3   | O limite diário do "Tirar dúvida" é conferido antes da chamada à IA, sem reserva: requisições paralelas passam do limite                              | `app/Actions/Lessons/AnswerStudyQuestion.php:78-96`                                                                                                        |
| C1    | C      | P3   | Duplo clique em "gerar link" pode bater no índice único parcial e dar erro 500                                                                        | `app/Actions/Access/IssueAccessLink.php:41-49`, migração `…001100…:39`                                                                                     |
| C2    | C      | P3   | Mudar a data de um encontro que já tem chamada é permitido, inclusive para o futuro                                                                   | `app/Actions/Meetings/SaveMeeting.php:39`                                                                                                                  |
| C3    | C      | P3   | Criar lição "no domingo X" reativa em silêncio um domingo marcado "sem EBD" e mantém o motivo como título                                             | `app/Actions/Lessons/CreateLesson.php:60-73`                                                                                                               |
| C4    | C      | P3   | "Continua no próximo" num domingo antigo (preenchido depois) pula encontros já realizados                                                             | `app/Actions/Meetings/ContinueLessonNextMeeting.php:46-62`                                                                                                 |
| C5    | C      | P3   | `/notificacoes/renovacao` sem CSRF e sem login aceita `old_endpoint` de terceiros                                                                     | `app/Http/Controllers/PushSubscriptionController.php:51-75`                                                                                                |
| C6    | C      | P3   | Não existe "reabrir encontro": `finished_at` nunca volta a nulo                                                                                       | `app/Actions/Meetings/FinishMeeting.php:28-32`                                                                                                             |
| D1–D6 | D      | —    | Código morto, duplicação e lógica de negócio em controllers                                                                                           | ver seção D                                                                                                                                                |

Não encontrei P0 nem P1. A autorização entre classes está bem feita: toda rota `encontros/{meeting}` passa por `ClassMeetingPolicy` (`app/Policies/ClassMeetingPolicy.php:13-25`), as rotas aninhadas usam `scopeBindings()` (`routes/web.php:126`, `:182`) e o MCP filtra tudo por `manageableClassroomIds()` (`app/Mcp/Concerns/ResolvesEbdModels.php:28-35`, `:61-66`, `:101-106`).

---

## A — Bugs confirmados

### A1. Uma inscrição push com chave inválida derruba os lembretes

- **Entrada:** um usuário logado faz `POST /notificacoes/inscricao` com `keys.p256dh` qualquer (ex.: `"abc"`). Pode ser um navegador com defeito, uma extensão ou alguém de propósito. A regra só exige `string|max:255` (`app/Http/Controllers/PushSubscriptionController.php:21-26`) e o valor é gravado (`:82`).
- **Caminho:** às 09:00, `ebd:remind-readings` → `SendReadingReminders::handle` percorre as classes (`app/Actions/Notifications/SendReadingReminders.php:43`) → `WebPushSender::send` enfileira todas as inscrições (`app/Support/Push/WebPushSender.php:43`) → `flush()` chama `prepare()` para o lote inteiro antes de enviar (`vendor/minishlink/web-push/src/WebPush.php:179`) → `Encryption::deterministicEncrypt` → `Utils::unserializePublicKey` lança `InvalidArgumentException` (`vendor/minishlink/web-push/src/Utils.php:48`). Nada captura a exceção em `WebPushSender` nem em `SendReadingReminders`.
- **Resultado errado:** ninguém daquela classe recebe o lembrete, e as classes que vêm depois na ordem também não, porque o laço é abortado. O mesmo vale para `SendLessonReminder` (`app/Actions/Notifications/SendLessonReminder.php:58`). Na publicação, a falha é engolida pelo listener (`app/Listeners/NotifyClassroomOfLessonPublished.php:20-25`) e o professor vê "o aviso falhou".
- Probabilidade: baixa a média (basta um registro ruim). Impacto: médio, porque os lembretes param para várias classes sem aviso visível. Confiança: alta (caminho lido até o vendor). Custo: P. Risco de regressão: baixo.
- **Correção mínima:** validar o formato da chave (base64url, 65 bytes decodificados começando com `0x04`; `auth` com 16 bytes) e, em `WebPushSender::send`, montar cada `Subscription` dentro de um `try`, descartando e registrando a inscrição inválida. Opcionalmente, envolver cada classe em `try/report` nos dois lembretes.

### A2. Lição que continua: "Você já leu hoje" para quem não leu hoje

- **Entrada:** a lição X ocupa os domingos D1 e D2 ("continua no próximo"). Na segunda-feira da semana de D2, Ana não leu nada, mas tinha marcado a leitura de segunda na semana de D1.
- **Caminho:** `CurrentLessonQuery` devolve D2/X; `readingWeekday` devolve 1; a lista de quem "já leu" filtra só por `lesson_id` e `weekday`, sem olhar `read_on` (`app/Actions/Notifications/SendReadingReminders.php:76-80`); a partição manda Ana para `$read` (`:82`).
- **Resultado errado:** às 20h, Ana recebe "Você já leu hoje. Que tal relembrar…" (`:155`) em vez de "Ainda dá tempo". A causa está no modelo: o índice único é `(user_id, lesson_id, weekday)` (migração `2026_09_23_001500_add_weekday_to_reading_checkins.php:40`), então só existe um registro por dia do plano em toda a vida da lição.
- Probabilidade: média (lições que continuam são comuns). Impacto: baixo, porque a mensagem é falsa mas inofensiva. Confiança: alta. Custo: P se o filtro passar a usar `read_on >= readingWeekStart(meeting)`, M se o modelo mudar. Risco: baixo.

### A3. "Encerrar aula" com "continua" num encontro sem lição: escrita parcial

- **Entrada:** `POST /admin/encontros/{id}/encerrar` com `continues=true` num encontro só com título (ex.: "Culto de missões") ou numa data sem lição. Pela tela é raro, porque o Modo Domingo parte de uma lição; pelo MCP (`finish_meeting`), não.
- **Caminho:** `FinishMeeting` grava `status=held`, `notes` e `finished_at` (`app/Actions/Meetings/FinishMeeting.php:28-32`) **fora de transação** e só depois chama `ContinueLessonNextMeeting` (`:34`), que lança `ValidationException` porque `lesson_id === null` (`app/Actions/Meetings/ContinueLessonNextMeeting.php:31-33`).
- **Resultado errado:** a pessoa recebe o erro "Só é possível continuar um encontro que tem lição", mas o encontro já está encerrado e realizado. No MCP, `runAudited` repassa a `ValidationException` sem gravar o histórico (`app/Mcp/Tools/EbdTool.php:151-160`), então a alteração fica sem registro em `audit_logs`.
- Probabilidade: baixa. Impacto: baixo (estado coerente, mas inesperado, e sem histórico). Confiança: alta. Custo: P (validar antes de gravar ou envolver tudo numa transação). Risco: baixo.

### A4. E-mail com outra caixa de letras dá 500 na edição do aluno

- **Entrada:** o professor edita um aluno sem senha e digita `Maria@Gmail.com`, sendo que `maria@gmail.com` já pertence a outra conta.
- **Caminho:** a regra `unique:users,email,{id}` roda sobre o valor como foi digitado (`app/Http/Controllers/Admin/StudentController.php:182`), e no PostgreSQL a comparação diferencia maiúsculas. O valor só vira minúsculo depois da validação (`:185-187`), e `UpdateStudentProfile` salva (`app/Actions/Classrooms/UpdateStudentProfile.php:48-51`). O índice `users_email_unique` (migração `0001_01_01_000000_create_users_table.php:17`) recusa.
- **Resultado errado:** `QueryException`, ou seja, erro 500 com a página de erro, em vez de "e-mail já usado". Os outros caminhos normalizam antes de validar (`app/Http/Requests/CompleteProfileRequest.php`, `prepareForValidation`; `ProfileUpdateRequest`; Fortify com `lowercase_usernames`), então só este ficou de fora.
- Probabilidade: baixa. Impacto: baixo. Confiança: alta. Custo: P (normalizar com `merge` antes do `validate`). Risco: baixo.

### A5. Telefone de 21 a 30 dígitos estoura a coluna

- **Entrada:** colar dois números no campo, ex.: `11 98765-4321 11 91234-5678` (22 dígitos). A regex aceita até 30 caracteres de `[0-9 ()+.-]` (`app/Concerns/StudentValidationRules.php:32`).
- **Caminho:** `normalizePhone` mantém só os dígitos (`app/Actions/Classrooms/CreateManagedStudent.php:59`) e devolve 22 caracteres; a coluna é `string('phone', 20)` (migração `2026_09_23_001100_create_access_links_table.php:18`).
- **Resultado errado:** erro 500 ao cadastrar o aluno, editá-lo, completar o cadastro, editar o perfil ou usar o MCP (`add_managed_student`/`update_student`).
- Probabilidade: baixa. Impacto: baixo. Confiança: alta. Custo: P (limitar a 15 dígitos depois de normalizar, que é o limite E.164). Risco: baixo.

---

## B — Riscos demonstráveis

### B1. Sequência de dias quebra na 2ª semana de uma lição que continua

- Na semana de D2, a semana de estudo mostra como lidos os dias marcados na semana de D1 (`app/Queries/StudyWeekQuery.php:77`, por `lesson_id`). Se o aluno tocar de novo, `insertOrIgnore` não grava nada por causa do índice `(user, lesson, weekday)` (`app/Actions/Engagement/RecordReadingCheckin.php:39`), e o `read_on` de hoje (`:44`) nunca é registrado.
- `StudyStreak::dates` conta só `read_on` e presenças (`app/Support/StudyStreak.php:62-80`). A sequência zera durante a semana e os selos `Streak7`/`Streak30` ficam mais difíceis, a menos que o aluno desmarque e marque de novo.
- Depende de uma decisão de produto: o "dia do plano lido" vale para a lição ou para a semana do encontro? A migração mostra que a escolha foi deliberada (comentário em `2026_09_23_001500…:27`), mas o efeito na sequência e no lembrete (A2) parece não ter sido previsto. Sev. P2 (desmotiva o aluno justamente nas lições mais longas). Prob. média. Confiança: alta no comportamento, média em ser indesejado. Custo: M. Risco de regressão: médio (selos, progresso, relatórios).

### B2. Chamada salva ao mesmo tempo por dois aparelhos

- `RecordAttendance` lê o elenco e os presentes anteriores fora da transação (`app/Actions/Meetings/RecordAttendance.php:42-49`). Dentro dela, apaga quem não está na lista enviada (`:56-59`) e faz `insertOrIgnore` dos enviados (`:68`), sem `lockForUpdate` no encontro.
- Professor 1 envia {1,2} e professor 2 envia {1,3} ao mesmo tempo. Com um entrelaçamento possível (os dois apagam, depois os dois inserem), o resultado é {1,2,3}, que nenhum dos dois enviou. A chamada salva a cada toque (comentário em `:77`), o que aumenta a janela. Os selos são calculados sobre `$before`, que pode estar velho.
- Prob. baixa (exige dois professores da mesma classe na mesma chamada). Impacto: baixo (corrigível na tela). Custo: P (`ClassMeeting::lockForUpdate()->find()` no início da transação). Risco: baixo. O modo `add` do MCP (`app/Mcp/Tools/RecordAttendanceTool.php`) faz ler-somar-gravar e tem a mesma janela.

### B3. Data de encontro sem validação de domingo

- `meetingRules` exige só `required|date` (`app/Concerns/MeetingValidationRules.php:22`). `meeting_on` na criação de lição, só `nullable|date` (`app/Http/Requests/Admin/LessonRequest.php:57`). O MCP usa as mesmas regras (`app/Mcp/Tools/SaveMeetingTool.php`). Só `PlanMeetings` e `FillSundays` garantem domingo.
- Um encontro numa quarta-feira vira a "lição da semana" (`app/Queries/CurrentLessonQuery.php:29-36`). A semana de leitura passa a ser de segunda a quarta (`app/Support/ChurchCalendar.php:62-71`), o que corta quinta a sábado e a leitura de "domingo" (weekday 7). O lembrete de véspera só roda aos sábados (`routes/console.php:29`) e não avisa. `FillSundays` cria o domingo da mesma semana, que fica vazio ao lado.
- Pode ser intencional (evento especial num sábado), então a decisão é de produto. Recomendação: validar `Carbon::parse(...)->isSunday()` por padrão, ou avisar quando não for domingo. Prob. baixa. Impacto: médio quando acontece. Custo: P. Risco: baixo (os testes usam domingos).

### B4. "Teve aula" aceita encontro cancelado

- `MeetingStatusController::held` confere só a data futura (`app/Http/Controllers/Admin/MeetingStatusController.php:55`) e grava `Held` (`:61`). A tela só mostra o botão para `planned` sem chamada (`resources/js/pages/admin/classrooms/meetings/show.tsx:273-275`), mas o servidor não repete essa regra.
- Um `POST /admin/encontros/{cancelado}/realizado` deixa o domingo "realizado", sem lição e com o motivo do cancelamento como título, e ele passa a contar como "com aula" no relatório. Prob. baixa (exige chamada direta à rota). Impacto: baixo. Custo: P. Risco: baixo.

### B5. Cancelamento e chamada concorrentes

- `CancelMeeting` confere `hasAttendance()` antes de abrir a transação (`app/Actions/Meetings/CancelMeeting.php:26-32`). Uma chamada gravada entre a conferência e o `update` fica num encontro "sem EBD", e `RecordAttendance` recusa editar encontro cancelado. Prob. muito baixa. Custo: P (trava o encontro dentro da transação e confere ali).

### B6. Limite diário do "Tirar dúvida" pode ser ultrapassado

- `remainingToday` é conferido (`app/Actions/Lessons/AnswerStudyQuestion.php:78`), depois vem a chamada à OpenAI, que pode levar segundos (`:84`), e só então a pergunta é gravada (`:96`). Até 5 requisições por minuto passam no throttle (`app/Providers/AppServiceProvider.php:124`) e todas veem o mesmo saldo. O custo extra é limitado (5 por minuto por pessoa). Custo: P (trava consultiva por usuário ou reserva gravada antes). Prob. baixa. Impacto: baixo.

---

## C — Hipóteses

- **C1. Duplo clique em "gerar link".** Duas transações de `IssueAccessLink` revogam o link ativo e inserem um novo (`app/Actions/Access/IssueAccessLink.php:41-49`). O índice único parcial `access_links_one_active_per_user` (migração `…001100…:39`) faria a segunda falhar com erro 500. Não verifiquei se o botão fica desabilitado durante o envio. Confiança: média.
- **C2. Mover a data de um encontro com chamada.** `SaveMeeting` aceita trocar `held_on` em qualquer encontro (`app/Actions/Meetings/SaveMeeting.php:39`), e a tela oferece "Editar data e título" sempre (`show.tsx:294-299`). Levar um domingo realizado para o futuro deixa presenças numa data que ainda não chegou e muda `Enrollment::sinceMap`. Corrigir uma data errada é útil, mas mover para o futuro provavelmente não.
- **C3. Criar lição "no domingo X" reativa um domingo cancelado.** `CreateLesson::scheduleOn` encontra o domingo cancelado (que fica sem lição), atribui a lição e força `Planned` (`app/Actions/Lessons/CreateLesson.php:60-73`), mas o título continua sendo o motivo do cancelamento. O professor pode não perceber que desfez o "sem EBD".
- **C4. "Continua" num domingo antigo.** Quando o domingo seguinte já está `Held`, `ShiftPlannedLessons` só mexe nos `Planned` (`app/Actions/Meetings/ShiftPlannedLessons.php:21-28`), então a lição "continua" depois do realizado, não no domingo seguinte (`app/Actions/Meetings/ContinueLessonNextMeeting.php:46-62`). Só acontece quando o professor preenche a agenda depois. Confiança: média.
- **C5. Renovação push sem login.** `POST /notificacoes/renovacao` não tem CSRF (`bootstrap/app.php:30`) e, com `old_endpoint`, atribui a nova inscrição ao dono da antiga (`app/Http/Controllers/PushSubscriptionController.php:58-71`). Quem conhecesse o endpoint de outra pessoa (URL longa e difícil de adivinhar) poderia desviar as notificações dela. Risco real baixo.
- **C6. Sem "reabrir encontro".** Nada volta `finished_at` para nulo, e `ClassroomOverviewQuery` usa esse campo para virar o cartão da semana (`app/Queries/ClassroomOverviewQuery.php:236`). Um "encerrar" por engano no domingo faz o Resumo pular para o próximo domingo antes da hora. A chamada continua editável. É mais uma lacuna de produto que um defeito.

---

## D — Preferências e higiene

- **D1. Código morto (conferido com grep):** `SeriesPolicy::viewReport` (`app/Policies/SeriesPolicy.php:25`; a rota antiga só redireciona, `routes/web.php:165`), `ClassMeetingPolicy::delete` (`app/Policies/ClassMeetingPolicy.php:18`; não há rota nem ferramenta de apagar encontro), o comando `inspire` (`routes/console.php:12`) e os dois `ExampleTest` (`tests/Unit/ExampleTest.php`, `tests/Feature/ExampleTest.php`; o de Feature até cobre a home, mas `HomeTest` já faz isso).
- **D2. Regras de negócio em controllers, ao lado de Actions:** `MeetingStatusController::held` e `::restore` (`:51-86`, arquivo `app/Http/Controllers/Admin/MeetingStatusController.php`) mudam o status direto, enquanto `cancel` e `continue` usam Actions. `LessonController::destroy` (`app/Http/Controllers/Admin/LessonController.php:190-203`) libera domingos dentro do controller. `ClassroomMemberController::destroy/restore` concentram remoção, revogação e desfazer (`app/Http/Controllers/Admin/ClassroomMemberController.php:31-110`). `ReadingCheckinController::destroy` também. Não é defeito, mas o MCP não consegue reaproveitar essas regras, e B4 nasceu daí. Vale extrair só quando a regra for usada em dois lugares.
- **D3. Duplicação:** "posição da lição" (`ClassMeetingController::positions` e `CurrentLessonQuery::position`) e o aviso de "ficou sem data" (`MeetingStatusController::warnLeftover` e `app/Http/Controllers/Admin/AttendanceController.php:47-49`).
- **D4. `LessonPolicy::view` e `Lesson::visibleTo` precisam ficar em sincronia na mão** (comentário em `app/Policies/LessonPolicy.php:17`). Hoje batem. Um teste de paridade (ver 16-test-strategy) protege isso melhor que uma refatoração.
- **D5. Efeito colateral em GET:** `ClassMeetingController::index` chama `FillSundays` (`app/Http/Controllers/Admin/ClassMeetingController.php:46`). É idempotente e protegido por `insertOrIgnore`, então tudo bem, mas vale saber que um GET grava.
- **D6. Geração de áudio no `terminating`:** é uma escolha consciente na falta de worker de fila (`app/Actions/Lessons/RequestLessonAudio.php:610-619`). Com `QUEUE_CONNECTION=sync` nos testes e `Http::preventStrayRequests()` em `LessonAudioTest`, o risco nos testes está controlado.

---

## Verificações sem achado (o que foi conferido e está correto)

- **Autorização entre classes:** todas as rotas de `admin` (`routes/web.php:107-196`) passam por Gate ou Form Request com policy. As rotas aninhadas de lição e de encontro usam `scopeBindings()`. Material e áudio seguem `LessonPolicy::view` e, para conteúdo do professor, `viewTeacherContent` (`MaterialFileController.php:26-33`, `LessonAudioController.php:19`).
- **Conteúdo do professor fora da busca e da IA:** `SyncLessonSearchText` indexa só blocos de aluno. `AnswerStudyQuestion::context` filtra `ContentAudience::Student`. `LessonController::present` filtra na própria consulta.
- **Link pessoal:** só o hash é guardado, a mensagem de erro é genérica, o link nunca autentica professor ou admin (`app/Actions/Access/LoginWithAccessLink.php:28`), a remoção da classe revoga o link daquela classe e a mudança de classe repassa o link ativo (`app/Actions/Classrooms/MoveStudentToClassroom.php:35`).
- **Frequência duplicada:** há índice único `(class_meeting_id, user_id)` (migração `…001300…:20`) mais `insertOrIgnore`.
- **Leitura marcada duas vezes:** é idempotente pelo índice `(user, lesson, weekday)`.
- **Fuso:** `ChurchCalendar` concentra o "hoje" da igreja, as datas são comparadas como texto `Y-m-d`, os agendamentos usam `->timezone($timezone)` e `Enrollment` converte `created_at` com `AT TIME ZONE`.
- **Agenda:** `FillSundays` usa `insertOrIgnore` sobre o índice `(classroom_id, held_on)`. `ShiftPlannedLessons` usa `lockForUpdate` dentro das transações de `CancelMeeting` e `ContinueLessonNextMeeting`. "Continua" é idempotente: na segunda vez, o domingo seguinte já tem a mesma lição.
