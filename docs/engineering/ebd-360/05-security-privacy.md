# EBD 360 — 05. Segurança e privacidade

Auditoria defensiva, só leitura, feita em 2026-10-19 sobre a branch `engineering/ebd-360` (commit `e03fd30`).
Nada foi executado contra produção ou homologação, nenhum teste rodou contra o banco e nenhum segredo do `.env` foi lido nem copiado.
Toda evidência está no formato `caminho:linha`.

Legenda: **P0** crítico (corrigir já) · **P1** alto · **P2** médio · **P3** baixo.
Classes: **A** bug confirmado (caminho lido de ponta a ponta) · **B** risco demonstrável · **C** hipótese (precisa confirmar no ambiente) · **D** preferência ou endurecimento.

---

## 1. Resumo executivo

No geral a base está bem protegida. As policies são centralizadas (`canManageClassroom`), as rotas aninhadas usam `scopeBindings()` ou policies por recurso, todas as ferramentas MCP passam pela mesma policy das telas e gravam auditoria, o conteúdo do professor é filtrado já na consulta, o Markdown é renderizado com `html_input: strip`, o token do link pessoal tem cerca de 285 bits de entropia, fica só como hash no banco e vai no fragmento (`#`) da URL, e os arquivos de material ficam fora da pasta pública.

O ponto que pede atenção:

1. **(A, P1) Um professor consegue tomar a conta de qualquer aluno da igreja, inclusive de outra classe.** Basta adicionar a pessoa à própria classe pelo e-mail e gerar um "link de recuperação". Ao entrar pelo link, a senha da vítima é apagada e o professor fica logado como ela por até 400 dias. Com isso ele lê as anotações pessoais, as dúvidas e o perfil da pessoa e vê as lições restritas da outra classe. É a única quebra real de isolamento entre classes encontrada.

Riscos médios:

2. **(B, P2) SSRF cego pela inscrição de push.** Qualquer conta logada, inclusive uma criada pelo autocadastro, registra um `endpoint` arbitrário (por exemplo `http://serviço.railway.internal:porta/`) e dispara um POST do servidor para ele com "Testar notificação". O toast mostra se a resposta foi 2xx, então serve para varrer portas.
3. **(B, P2) O link pessoal não expira e pode ser usado várias vezes.** Sem `EBD_ACCESS_LINK_TTL_DAYS` e até o aluno criar senha, quem tiver o link (encaminhado no WhatsApp, por exemplo) entra como o aluno, em quantos aparelhos quiser.

O resto é P3 ou endurecimento: falta HSTS e CSP, falta auditoria das ações sensíveis feitas pela web, há corrida no limite diário da IA, enumeração de e-mail e alguns metadados de domingo aparecem em lição pública.

---

## 2. Modelo de ameaças (curto)

| Ator                                                                        | Confiança                                      | O que pode tentar                                                                                                                         |
| --------------------------------------------------------------------------- | ---------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------- |
| Visitante anônimo                                                           | nenhuma                                        | ler lição restrita ou rascunho, baixar materiais, forçar link pessoal, `POST /notificacoes/renovacao` (sem CSRF), registrar cliente OAuth |
| Conta autocadastrada (registro ligado por padrão, `config/fortify.php:147`) | baixa                                          | tudo do anônimo, mais rotas `auth`: push (SSRF), anotações, leituras                                                                      |
| Aluno                                                                       | média                                          | ler dados de colegas, ver conteúdo do professor, abusar da IA (custo), descobrir quem perguntou o quê                                     |
| Professor da classe A                                                       | média-alta (voluntário)                        | ver ou alterar dados da classe B, tomar conta de aluno, abusar da geração de áudio                                                        |
| Agente MCP (token OAuth de um professor)                                    | igual ao professor, sujeito a prompt injection | as mesmas escaladas do professor, por ferramenta                                                                                          |
| Administrador                                                               | total                                          | fora do escopo                                                                                                                            |

Ativos: anotações pessoais (`lesson_notes`), dúvidas (`study_questions`), anotações do professor sobre alunos (`student_notes`), PII de alunos (telefone, nascimento, gênero, e-mail, foto, muitos deles menores de idade), conteúdo do professor, chaves OpenAI e VAPID, sessões e cookies "lembrar".

---

## 3. Tabela de achados

| ID   | Classe | Sev.   | Título                                                                                                                                   | Prob.       | Impacto                        | Confiança | Custo                              | Risco de regressão                    |
| ---- | ------ | ------ | ---------------------------------------------------------------------------------------------------------------------------------------- | ----------- | ------------------------------ | --------- | ---------------------------------- | ------------------------------------- |
| S-01 | A      | **P1** | Professor toma conta de aluno de outra classe (adicionar por e-mail + link de recuperação)                                               | média       | alto                           | alta      | baixo-médio                        | médio (mexe na recuperação de acesso) |
| S-02 | B      | **P2** | SSRF cego com oráculo pelo `endpoint` de push                                                                                            | baixa-média | médio                          | alta      | baixo                              | baixo                                 |
| S-03 | B      | **P2** | Link pessoal sem validade e reutilizável                                                                                                 | média       | médio-alto                     | alta      | baixo (config) / médio (uso único) | médio                                 |
| S-04 | B      | P3     | Ações sensíveis da web sem AuditLog (link, e-mail, vínculo, remoção)                                                                     | —           | médio (forense)                | alta      | médio                              | baixo                                 |
| S-05 | B      | P3     | Adicionar por e-mail revela se a conta existe e expõe a PII de qualquer conta ao professor                                               | média       | baixo-médio                    | alta      | baixo                              | baixo                                 |
| S-06 | B      | P3     | Corrida no limite diário do "Tirar dúvida"                                                                                               | baixa       | baixo (custo)                  | alta      | baixo                              | baixo                                 |
| S-07 | B      | P3     | Anonimato da aba Dúvidas é frágil em turma pequena (`people` + data)                                                                     | média       | baixo-médio                    | alta      | baixo                              | baixo                                 |
| S-08 | B      | P3     | Lição pública expõe `visitors_count`, `has_attendance` e o motivo do "sem EBD"                                                           | alta        | baixo                          | alta      | baixo                              | baixo                                 |
| S-09 | B      | P3     | Sequestro de inscrição push de outra pessoa conhecendo o `endpoint` (`store`/`renew`)                                                    | muito baixa | baixo                          | alta      | baixo                              | médio (fluxo do SW)                   |
| S-10 | B      | P3     | Recuperação de senha do Fortify ativa no backend, mesmo com a flag desligada                                                             | baixa       | baixo-médio                    | média     | baixo                              | baixo                                 |
| S-11 | B      | P3     | Link pessoal em claro fica no payload da sessão (flash), sessão sem criptografia                                                         | baixa       | médio                          | média     | baixo                              | baixo                                 |
| S-12 | D      | P3     | Sem HSTS e sem CSP                                                                                                                       | —           | médio (defesa em profundidade) | alta      | baixo (HSTS) / médio (CSP)         | baixo / médio                         |
| S-13 | C      | P3     | `trustProxies('*')`: IP do rate limit pode ser forjável conforme a cadeia de proxies do Railway                                          | ?           | baixo                          | baixa     | baixo                              | baixo                                 |
| S-14 | B      | P3     | Geração de áudio sem teto por professor/dia, rodando no processo web                                                                     | baixa       | baixo (custo/DoS)              | alta      | baixo                              | baixo                                 |
| S-15 | D      | P3     | `Cache-Control: private, max-age` em material ou áudio restrito, e `public` em material de lição pública que depois muda de visibilidade | baixa       | baixo                          | alta      | baixo                              | baixo                                 |
| S-16 | D      | P3     | Autocadastro sem rate limit nem CAPTCHA (habilita S-02 em escala)                                                                        | baixa       | baixo                          | média     | baixo                              | baixo                                 |
| S-17 | D      | P3     | PII de alunos enviada a terceiros (MCP → assistente de IA; dúvidas → OpenAI) sem aviso explícito                                         | —           | LGPD                           | alta      | baixo (texto)                      | nenhum                                |

---

## 4. Detalhamento

### S-01 (A, P1): professor de A toma a conta de um aluno de B

**Caminho completo:**

1. `POST /admin/classes/{A}/membros` com `{email: vitima@…, role: student}` (`routes/web.php:153`). `ClassroomMemberRequest::authorize` só exige `manageMembers` na classe A quando o papel é aluno (`app/Http/Requests/Admin/ClassroomMemberRequest.php:13-22`). `AddClassroomMember` procura **qualquer** conta pelo e-mail (`app/Actions/Classrooms/AddClassroomMember.php:19`) e a vincula como aluno sem aviso nem consentimento (`:35-37`). A trava existente só impede trocar o papel de quem **já** está na classe (`:27`).
2. `POST /admin/classes/{A}/membros/{vitima}/link` (`routes/web.php:158`). O controller só confere `isMemberOf(A)` (`app/Http/Controllers/Admin/StudentAccessController.php:58`). `IssueAccessLink` recusa só professores e administradores (`app/Actions/Access/IssueAccessLink.php:29-35`) e, por regra, aceita conta **com senha** ("link de recuperação", comentário em `:16-20`).
3. O professor abre o link numa janela anônima. `LoginWithAccessLink` **apaga a senha** da vítima (`app/Actions/Access/LoginWithAccessLink.php:36-37`) e faz login com "lembrar" de 400 dias (`:41-46`, `config/ebd.php:33`).
4. Logado como a vítima, ele vê as anotações pessoais de cada lição (`app/Http/Controllers/LessonController.php:127`), as lições restritas da classe B (`app/Policies/LessonPolicy.php:31`) e o perfil, e pode trocar e-mail e senha em "Completar cadastro" (`app/Actions/Access/CompleteStudentProfile.php:23-35`). A vítima perde o acesso.

**Resultado errado:** o isolamento entre classes vale para as telas da classe B, mas não para a **conta** do aluno de B. Mesmo sem o passo 1, um aluno que esteja em A e em B fica exposto ao professor de A. O único rastro é `access_links.created_by`, porque não há AuditLog na web (S-04).

**Correção sugerida (exige decisão do PO, ver seção 6):** só permitir link de recuperação para conta **com senha** quando todas as classes da pessoa forem gerenciadas por quem gera o link (ou só para administradores). Alternativa mínima: adicionar por e-mail só vincula quem não tem nenhuma classe ou exige convite aceito. Registrar AuditLog na emissão.

**Teste que prova:** feature test. O professor T gerencia só A. O aluno S tem senha e é aluno só de B. T faz `POST admin.classrooms.members.store(A, email S, student)`, depois `POST admin.classrooms.members.link.store(A, S)`, pega o token do flash `accessLink`, faz logout e `POST /entrar {token}`. Esperado hoje: `assertAuthenticatedAs(S)` e `S->password === null`. Esperado depois da correção: 403/422 em um dos passos.

### S-02 (B, P2): SSRF cego pelo endpoint de push

- A validação aceita qualquer URL (`app/Http/Controllers/PushSubscriptionController.php:22`, regra `url`, sem restrição de host ou esquema).
- `store` está aberto a qualquer conta logada (`routes/web.php:55`), e o autocadastro está ligado (`config/fortify.php:147`).
- `POST /notificacoes/teste` (`routes/web.php:57`) envia na hora para os aparelhos da pessoa (`PushSubscriptionController.php:98-114`). O `WebPushSender` faz POST para o endpoint (`app/Support/Push/WebPushSender.php:44-49`), conta 2xx como entregue (`:56`), e o toast diz "enviada para N aparelho(s)" ou dá erro (`PushSubscriptionController.php:120-122`). Isso é um oráculo booleano de "esse host interno respondeu 2xx a um POST".
- Limite: 60 por minuto por usuário (`app/Providers/AppServiceProvider.php:122`).
- Impacto: dá para varrer `*.railway.internal`, `169.254.169.254` e `localhost:porta`. O corpo vai cifrado (aes128gcm), então dificilmente aciona ações, mas o endereço e o método são do atacante.
- **Correção (baixo risco):** aceitar só `https://` e hosts dos serviços de push conhecidos (`fcm.googleapis.com`, `updates.push.services.mozilla.com`, `*.notify.windows.com`, `web.push.apple.com`, `*.push.apple.com`), em `RULES` e em `renew`.
- **Teste:** `POST push.store` com `endpoint=http://127.0.0.1:1/x` → hoje 302 com sucesso. Esperado: erro de validação em `endpoint`.

### S-03 (B, P2): link pessoal sem validade e reutilizável

- `ttl_days` é nulo por padrão (`config/ebd.php:34`, `.env.example:26`), então `expires_at = null` (`app/Actions/Access/IssueAccessLink.php:48`).
- O link não é de uso único: cada uso só incrementa `use_count` (`app/Actions/Access/LoginWithAccessLink.php:51-54`). Ele só é revogado quando o aluno cria senha (`CompleteStudentProfile.php:37-39`), quando um novo é gerado ou quando o acesso é bloqueado.
- Cenário: o professor manda o link no grupo por engano, ou o aluno encaminha a mensagem. Qualquer pessoa entra como ele e ainda pode criar a senha antes dele.
- **Correção:** (a) baixo risco, só configuração: definir `EBD_ACCESS_LINK_TTL_DAYS` (por exemplo 14) em produção. (b) Médio: invalidar o link no primeiro uso em conta sem senha, ou depois do primeiro aparelho, já que o "lembrar" de 400 dias cobre o resto. É decisão do PO (seção 6).
- **Teste:** emitir link, fazer `POST /entrar` duas vezes com sessões diferentes → hoje as duas autenticam.

### S-04 (B, P3): falta auditoria nas ações sensíveis da web

`AuditLogger::record` só é chamado pelas ferramentas MCP (`app/Mcp/Tools/EbdTool.php:168-171`, `source => 'mcp'` em `app/Support/Audit/AuditLogger.php`). Emitir link, revogar, adicionar por e-mail, remover membro, mudar e-mail de aluno, mover de classe e publicar lição pela web não deixam histórico. Isso agrava S-01, porque não dá para responder "quem entrou na conta de quem".
**Correção:** chamar `AuditLogger` com `source => 'web'` nos pontos citados. **Teste:** `assertDatabaseHas('audit_logs', ['tool' => 'issue_access_link'])` depois de emitir.

### S-05 (B, P3): enumeração de e-mail e PII pelo "adicionar por e-mail"

`AddClassroomMember.php:21-25` responde "Nenhuma conta encontrada", o que confirma se o e-mail existe. Quando existe, a pessoa entra na classe sem consentimento e a ficha mostra e-mail, telefone e nascimento dela (`app/Http/Controllers/Admin/StudentController.php:117-122`). É parte da mesma causa de S-01.

### S-06 (B, P3): corrida no limite diário da IA

`AnswerStudyQuestion::handle` confere `remainingToday` (`app/Actions/Lessons/AnswerStudyQuestion.php:79`), chama a OpenAI (segundos) e só depois grava (`:96`). Requisições paralelas passam pela checagem juntas. O throttle `study-helper` de 5 por minuto (`AppServiceProvider.php:124`) limita o estrago a cerca de 4 extras por minuto.
**Correção:** `Cache::lock("helper:{$user->id}")` ou gravar a pergunta antes de chamar a API. **Teste:** limite 1 com cliente HTTP falso lento e duas requisições concorrentes → as duas respondem.

### S-07 (B, P3): anonimato da aba Dúvidas

O payload **não** vaza `user_id` nem nome. O controller seleciona `user_id` (`app/Http/Controllers/Admin/ClassroomQuestionController.php:37`), mas só usa o campo para contar `people` (`:53`) e excluir professores (`:33`). Mesmo assim, `people: 1` junto com `asked_on` (`:63`) e o texto da pergunta identifica o aluno numa turma pequena. A exclusão de "staff" só considera professores **desta** classe (`:28`).
**Correção:** omitir `people` e `asked_on` quando `people < 3`, ou mostrar só a semana. É decisão do PO.

### S-08 (B, P3): metadados de domingo em lição pública

`LessonController::present` carrega `meetings` ativos (`app/Http/Controllers/LessonController.php:162`), e `ClassMeetingResource` sempre inclui `visitors_count`, `has_attendance` e `title` (o motivo do "sem EBD") (`app/Http/Resources/ClassMeetingResource.php:39,49-50`). Para visitante anônimo, isso expõe dados operacionais da classe.
**Correção (baixo risco):** só incluir `visitors_count` com `withNotes()` ou quando a pessoa gerencia a classe. **Teste:** GET anônimo de lição pública → hoje a prop `lesson.meetings[0].visitors_count` existe.

### S-09 (B, P3): sequestro de inscrição push

`save()` faz `updateOrCreate` por `endpoint` e troca o `user_id` (`PushSubscriptionController.php:82-89`). Isso é intencional ("o aparelho passa para quem loga", teste `test_the_same_device_moves_to_whoever_logs_in`). `renew` não tem auth nem CSRF (`routes/web.php:68`, `bootstrap/app.php:31`) e aceita `old_endpoint` para herdar o dono (`:58-62`). Quem souber o endpoint de outra pessoa redireciona as notificações dela para um endpoint próprio, com chaves próprias, e lê o conteúdo (nome, títulos de lição). O endpoint é um segredo de alta entropia, mas é gravado em log em `WebPushSender.php` (`Log::warning(... 'endpoint' ...)`). Risco residual baixo.

### S-10 (B, P3): reset de senha ativo no backend

`Features::resetPasswords()` está sempre ligado (`config/fortify.php:148`). A flag `ebd.password_reset_by_email` só muda a **tela** (`app/Providers/FortifyServiceProvider.php:65`). `POST /forgot-password` continua funcionando: confirma se o e-mail existe e, se `MAIL_MAILER=log` em produção, grava o link de reset no log.
**Correção:** só registrar `resetPasswords()` quando a flag estiver ligada.

### S-11 (B, P3): link em claro na sessão

O comentário diz que "o link em claro só existe nesta resposta" (`app/Http/Controllers/Admin/StudentAccessController.php:83`), mas `Inertia::flash` (`:86`) passa pelo armazenamento da sessão (`SESSION_DRIVER=database`, `SESSION_ENCRYPT=false`, `.env.example:49-51`) até o próximo request. Quem tiver leitura do banco (backup, réplica) vê tokens válidos por alguns instantes.
**Correção:** `SESSION_ENCRYPT=true` em produção ou devolver o link fora da sessão. Confirmar no Railway (C).

### S-12 (D, P3): headers

`SecurityHeaders` define XFO, nosniff, Referrer-Policy e Permissions-Policy (`app/Http/Middleware/SecurityHeaders.php:17-20`), mas não envia `Strict-Transport-Security` e não tem CSP (comentário em `:9`). HSTS tem custo baixo. Uma CSP mínima (`object-src 'none'; base-uri 'self'; frame-ancestors 'self'`) já ajuda, porque o app usa `dangerouslySetInnerHTML` em `resources/js/components/lesson/rich-text.tsx:51` e `resources/js/pages/library/index.tsx:261`, mesmo que ambos hoje recebam HTML sanitizado.

### S-13 (C, P3): IP real atrás do proxy

`trustProxies(at: '*')` (`bootstrap/app.php:25`) confia no peer imediato. Se o Railway tiver mais de um salto ou repassar o `X-Forwarded-For` do cliente sem sobrescrever, o IP usado em `access-link` (`AppServiceProvider.php:116-119`) e em `login` pode ser forjado. Na prática o impacto é baixo, já que o token de 48 caracteres não é forçável. Confirmar com um eco de `request()->ip()` em homologação enviando `X-Forwarded-For` falso.

### S-14 (B, P3): custo do áudio

`RequestLessonAudio` evita gerações simultâneas (`app/Actions/Lessons/RequestLessonAudio.php:44-58`) e não regera áudio atualizado (`:32`). Mas qualquer professor pode editar uma vírgula e regerar quantas vezes quiser, e cada geração roda no `terminating` do processo web (`:64-69`), ocupando um worker PHP por minutos. Sugestão: limite por dia por lição ou por professor.

### S-15 (D, P3): cache de arquivos

`MaterialFileController.php:63` e `LessonAudioController.php:43` usam `private, max-age` para conteúdo restrito, que fica no cache do aparelho mesmo depois do logout (aparelho compartilhado), e `public` para material de lição pública, que pode continuar em cache intermediário por 1 h depois de a lição virar restrita. Sugestão: `private, no-store` para restrito.

### S-16 (D, P3): autocadastro

O registro está ligado por padrão (`config/ebd.php:19`) e não tem throttle próprio. Contas novas não veem nada restrito, mas habilitam S-02 e o spam de contas.

### S-17 (D, P3): dados pessoais com terceiros

`Presenter::student` envia ao agente MCP telefone, e-mail, nascimento e gênero (`app/Mcp/Presenter.php:170-180`), e o texto das dúvidas vai para a OpenAI (`AnswerStudyQuestion.php:84-89`). É um fluxo legítimo, mas precisa de transparência (LGPD, menores). Ver seção 6.

---

## 5. Itens verificados e OK

**Isolamento entre classes (web):**

- Todas as rotas `admin/classes/{classroom}/...` chamam `Gate::authorize` com `manageContent`, `manageMembers`, `viewInsights` ou `viewStudentProgress` (`StudentController.php:49,104,152,174`, `ClassMeetingController.php:44,121`, `ClassroomReportController.php:36`, `ClassroomQuestionController.php:24`, `ClassroomOverviewController.php:19`, `StudentAccessController.php:28,57,70`, `ClassroomMemberController.php:33,77`).
- `viewStudentProgress` devolve 404 para quem não é aluno da classe (`app/Policies/ClassroomPolicy.php:57-68`). Aluno de B sob o slug de A não abre.
- `classes/{classroom}/domingos/{meeting}` usa `scopeBindings()` (`routes/web.php:121-123`). As rotas `encontros/{meeting}` e `anotacoes/{note}` usam policies baseadas em `classroom_id` (`ClassMeetingPolicy.php:13-25`, `StudentNotePolicy.php:14-18`).
- Lições: materiais, blocos e leituras estão em `scopeBindings()` (`routes/web.php:185-198`), com `authorize` nos FormRequests (`LessonMaterialRequest.php:21-27`, `LessonBlockRequest.php:13-18`, `LessonReadingRequest.php:13-18`). O reordenar confere se os IDs são exatamente os da lição (`app/Actions/Lessons/ReorderLessonItems.php:22-33`).
- Referências cruzadas validadas pela classe: `lesson_id` e `series_id` nos encontros (`app/Concerns/MeetingValidationRules.php:28,48`, `SaveMeeting.php:26-32`), `series_id` e `author_ids` na lição (`LessonRequest.php:35,52-53`), chamada só com alunos da classe (`RecordAttendance.php:43-54`), mover aluno exige `manageMembers` nas duas classes (`StudentController.php:174-179`).
- Índices de gestão (`lessons`, `series`, `classes`, painel) filtram por `manageableClassroomIds()`.
- "Desfazer" remoção usa URL assinada de 2 minutos, e o papel professor exige admin (`ClassroomMemberController.php:58-66,77-83`).

**MCP:**

- As 20 ferramentas resolvem a classe, a lição e o encontro dentro de `manageableClassroomIds()` (`app/Mcp/Concerns/ResolvesEbdModels.php:28-84,104-129`), resolvem alunos só pela relação `students()` da classe (`:149`), chamam a mesma policy (`EbdTool.php:58-71`) e só mexem em rascunho (`EbdTool.php:77-84`). Nenhuma ferramenta emite link de acesso nem apaga dados.
- `/mcp` exige `auth:api`, `can:access-admin` e throttle (`routes/ai.php:20-21`).
- O AuditLog guarda os argumentos (textos longos viram hash) e o antes e o depois, com retenção de 1 ano (`app/Models/AuditLog.php:89-92`).

**OAuth (Passport):**

- O registro dinâmico só aceita redirect em `https://claude.ai/`, `https://claude.com/` e `http://localhost`. O pacote acrescenta a `/` final, o que impede `claude.ai.evil.com` (`config/mcp.php`, `vendor/laravel/mcp/.../OAuthRegisterController.php:197-206`). Os clientes são públicos (`confidential: false`).
- O device code está desligado (`AppServiceProvider.php:34`). O token vale 1 dia e o refresh 30 dias (`:140-141`).
- A tela de consentimento recusa quem não é da gestão e escapa o nome do cliente (`resources/views/mcp/authorize.blade.php:1-5`).

**Privacidade:**

- Anotação pessoal (`lesson_notes`): só é lida pelo dono (`LessonController.php:127`) e gravada pelo dono (`LessonNoteController.php:25-36`). Não aparece em MCP, relatório, ficha do aluno nem props compartilhadas (grep sem outras ocorrências, exceto `MergeAccounts`).
- Anotações do professor sobre o aluno ficam só na ficha da gestão (`StudentController.php:139-151`).
- Dúvidas: o payload da aba não tem `user_id` nem nome (`ClassroomQuestionController.php:44-67`).
- Conteúdo do professor: os blocos e materiais `teacher` nem são carregados para aluno ou visitante (`LessonController.php:150-167`). O download confere `viewTeacherContent` (`MaterialFileController.php:30-33`). Os blocos do professor ficam fora do índice de busca e do trecho destacado da biblioteca (`SyncLessonSearchText.php:17-22`), e fora do contexto da IA (`AnswerStudyQuestion.php:132-135`). As notas do encontro só saem com `withNotes()` para quem conduz.
- Props compartilhadas (`HandleInertiaRequests.php:44-71`) trazem só dados do próprio usuário. Nada de outros usuários e nada de chaves privadas (só a VAPID pública).

**Links pessoais:**

- Token `Str::random(48)` (cerca de 285 bits), guardado como SHA-256 (`IssueAccessLink.php:37,46`, `AccessLink.php:43-46`), oculto em serialização (`AccessLink.php:29`), no fragmento da URL, aceito só por POST com CSRF e com formato validado (`AccessLinkController.php:31-35`).
- Mensagem de erro genérica, throttle de 10 por minuto e 50 por dia por IP, sem flash do `token` (`bootstrap/app.php:44`).
- Nunca autentica professor ou admin (`LoginWithAccessLink.php:29-31`). Novo link revoga o anterior. "Bloquear" troca o `remember_token` e apaga as sessões (`RevokeStudentAccess.php:18-25`). Trocar de classe move o link (`MoveStudentToClassroom.php:36`).
- Conta sem senha não entra pelo formulário, porque `Hash::check('')` é falso (`User.php:136-139`).

**Onboarding:** e-mail único e normalizado, senha só para quem não tem, sessão regenerada (`CompleteProfileRequest.php`, `CompleteStudentProfile.php:41-51`). O middleware libera só as rotas necessárias (`EnsureProfileIsComplete.php:20`).

**Downloads:** arquivo com nome aleatório no disco privado (`StoreLessonMaterial.php:62-66`), autorização pela lição, 404 em vez de 403, `nosniff`, inline só para uma lista fechada de MIME detectado pelo conteúdo (`MaterialFileController.php:17-21,42-44`). Sem path traversal: `path` vem do banco, gerado pelo `store()`. Avatar validado como imagem jpeg/png/webp com nome aleatório.

**XSS:** Markdown com `html_input: strip` e `allow_unsafe_links: false` (`app/Support/Markdown.php:21-25`). Os botões de referência bíblica usam `e()` no `data-bible`, e o texto vem já escapado do CommonMark (`BibleLinks.php:139-150`). O trecho destacado da biblioteca é escapado antes do `<mark>` (`LibrarySearch.php:100-113`). O embed de vídeo reaproveita só o ID validado (`LessonMaterialResource.php:58-66`). O `backUrl` do Modo Domingo é restrito a `/admin/` (`LessonController.php:47`).

**CSRF:** só `notificacoes/renovacao` fica fora (`bootstrap/app.php:31`), e lá a sessão só vale com cookie SameSite=Lax (`config/session.php`, padrão `lax`).

**Busca:** a tsquery é montada só com letras e números, até 8 termos, com bind (`LibrarySearch.php:76-90`). O ILIKE da gestão escapa `%` e `_`.

**IA (Tirar dúvida):** só membros da classe ou quem gerencia (`StudyQuestionController.php:37-41`), pergunta de 3 a 500 caracteres, limite diário mais throttle, contexto limitado a 60 mil caracteres, chave só no servidor.

**Sessão e cookies:** `http_only` ligado, `same_site=lax`, `secure` nulo (automático pelo request HTTPS, com `forceHttps` e `trustProxies`), driver `database`. Login com throttle de 5 por minuto por e-mail e IP (`FortifyServiceProvider.php:83-89`). Senha forte e `uncompromised` em produção (`AppServiceProvider.php:83-89`).

---

## 6. Pendências que exigem decisão do Product Owner

1. **Recuperação de acesso por link (S-01).** Qualquer professor da classe pode "resetar" a senha de um aluno com senha? Opções: (a) só administradores; (b) só se todas as classes do aluno forem do professor; (c) manter, mas com AuditLog e aviso ao aluno. E adicionar por e-mail deve exigir que a pessoa aceite?
2. **Validade do link (S-03).** Qual TTL (sugestão: 14 dias)? O link vira de uso único depois do primeiro aparelho?
3. **Anonimato das dúvidas (S-07).** Esconder a contagem e a data em turmas com menos de N alunos? Por quanto tempo guardar `study_questions` (hoje para sempre)?
4. **LGPD e menores (S-17).** Texto de transparência dizendo que as dúvidas vão para a OpenAI e que o professor pode usar um assistente de IA com os dados da classe. Política de retenção de `student_notes`, `lesson_notes` e `audit_logs`.
5. **Autocadastro (S-16).** Precisa continuar aberto? Se não, `EBD_REGISTRATION_ENABLED=false` também elimina boa parte da superfície de S-02.
6. **Teto de custo do áudio e da IA (S-06, S-14).** Limite diário por lição ou professor para o áudio? Teto global diário de perguntas?

### Correções de baixo risco, sem mudar regra de negócio

- S-02: lista de hosts permitidos e `https` obrigatório no `endpoint` de push.
- S-03(a): definir `EBD_ACCESS_LINK_TTL_DAYS` em produção (só configuração).
- S-04: AuditLog `source=web` nas ações sensíveis.
- S-06: lock por usuário no "Tirar dúvida".
- S-08: não enviar `visitors_count` para quem não gerencia a classe.
- S-10: condicionar `Features::resetPasswords()` à flag.
- S-11: `SESSION_ENCRYPT=true`.
- S-12: header HSTS, e depois uma CSP mínima.
- S-15: `no-store` para arquivos restritos.
