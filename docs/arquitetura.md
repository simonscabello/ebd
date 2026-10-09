# Arquitetura e decisões

Documento curto com o "porquê" das escolhas. Para rodar o projeto, veja o [README](../README.md).

## Visão geral

Monólito Laravel com Inertia + React. Sem API separada, sem microsserviços. A organização segue o que um desenvolvedor Laravel espera encontrar:

```
Request ─▶ Form Request (validação) ─▶ Controller (fino) ─▶ Policy (autorização)
                                              │
                                              ├─▶ Action (regra de negócio / escrita)
                                              └─▶ Query (leitura mais elaborada)
                                              │
                                              ▼
                                   API Resource ─▶ Inertia::render(page, props)
```

- **Actions** (`app/Actions`) concentram as regras: `CreateLesson`, `UpdateLesson`, `ChangeLessonStatus`, `GenerateLessonSlug`, `StoreLessonMaterial`, `ReorderLessonItems`, `AddClassroomMember`… Uma classe, um método `handle()`, injetável por container. CRUD trivial sem regra (editar uma leitura, por exemplo) fica no controller com `$model->update($request->validated())`.
- **Queries** (`app/Queries`) para leituras com regra: `CurrentLessonQuery` (lição da semana), `StudyWeekQuery`, `AttendanceBookQuery`, `HomeStudyQuery`, `ClassroomOverviewQuery` (gestão da classe) e `LibrarySearch` (busca).
- **Resources** (`app/Http/Resources`) definem o formato dos dados enviados às páginas. São os mesmos que uma API REST devolveria.
- **Sem repository pattern**: o Eloquent já é a camada de persistência; escopos (`Lesson::visibleTo()`, `ClassMeeting::active()`) cobrem a reutilização de consultas.
- **DTOs** não foram criados: os Form Requests já entregam arrays validados e as Actions documentam o que esperam.

## Modelagem

```
users ──< classroom_user >── classrooms ──< series ──< lessons ──< lesson_blocks
  │         (role: teacher|student)  │                   │   ├──< lesson_materials
  ├──< access_links                  │                   │   └──< lesson_readings
  ├──< reading_checkins >────────────┼───────────────────┤
  ├──< lesson_notes >────────────────┼───────────────────┘
  ├──< user_badges                   └──< class_meetings ──< attendances
  └──< lesson_authors                     (encontros: domingo x lição)
```

| Tabela               | Observações                                                                                                                                                                                                                                                                                                                                                                                                      |
| -------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `users`              | `is_admin` para a administração geral. `avatar_path`: foto de perfil no disco `public` (o app recorta e reduz para 512px antes de enviar). `email` e `password` são opcionais: aluno criado pelo professor entra pelo link pessoal até completar o cadastro. `phone` (só dígitos, com DDI) monta o link do WhatsApp. `birth_date` e `gender` (`male`/`female`) servem aos aniversários e ao relatório da classe. |
| `classroom_user`     | Papel **por classe** (`teacher`/`student`), com `unique(classroom_id, user_id)`. `created_at` é a data de entrada na classe (usada nos indicadores de presença).                                                                                                                                                                                                                                                 |
| `classrooms`         | Classe (Jovens, Adultos…). `Classroom` porque `Class` é palavra reservada.                                                                                                                                                                                                                                                                                                                                       |
| `series`             | A revista/trimestre de uma classe. Slug único por classe.                                                                                                                                                                                                                                                                                                                                                        |
| `lessons`            | Espelha a lição da revista: `number` (único por série), título, texto base, `key_verse`, `goal` e o estudo principal em Markdown (`content`). `status` é só editorial. `scheduled_for` é **cache** (ver abaixo).                                                                                                                                                                                                 |
| `class_meetings`     | **Encontros** da classe: `held_on`, `lesson_id` (opcional), `status` (`planned`/`held`/`cancelled`), `title`, `notes` (só professor), `attendance_taken_at`, `visitors_count`. Único por classe e dia.                                                                                                                                                                                                           |
| `lesson_blocks`      | Blocos além do estudo principal: `kind` (roteiro, se houver tempo, nota de precisão, notas, contexto, teologia, curiosidade, aplicação, conceito), `audience` (`teacher`/`student`), Markdown e `drip_weekday`.                                                                                                                                                                                                  |
| `lesson_materials`   | Uma tabela para todos os tipos (`pdf`, `file`, `link`, `video`, `audio`, `reference`). `audience` separa material só do professor (ex.: manual completo do NotebookLM).                                                                                                                                                                                                                                          |
| `lesson_readings`    | Leituras da semana; `weekday` ISO (1 = segunda … 7 = domingo) ou nulo.                                                                                                                                                                                                                                                                                                                                           |
| `bible_verses`       | Texto bíblico (uma versão, NAA), um versículo por linha com `book` (1–66, `App\Enums\BibleBook`), `chapter`, `verse`. Preenchida por `bible:import`, nunca por migration ou seed versionado: o texto tem direitos autorais e fica fora do Git.                                                                                                                                                                   |
| `access_links`       | Links pessoais de acesso. Só o hash sha256 do token; no máximo um ativo por pessoa (índice único parcial); contador de uso.                                                                                                                                                                                                                                                                                      |
| `reading_checkins`   | Leitura marcada: uma por pessoa, lição e dia do plano (`weekday`), marcável a qualquer momento. `read_on` guarda quando a pessoa marcou (fuso da igreja).                                                                                                                                                                                                                                                        |
| `lesson_notes`       | Anotação pessoal. **Privada**: não existe rota nem prop que a entregue a outra pessoa.                                                                                                                                                                                                                                                                                                                           |
| `user_badges`        | Selos pessoais; os "por trimestre" guardam `series_id`. Único por pessoa, selo e série.                                                                                                                                                                                                                                                                                                                          |
| `push_subscriptions` | Aparelhos inscritos nos lembretes (Web Push): `endpoint` único, chaves `p256dh`/`auth` do navegador. Um aparelho pertence a quem entrou por último nele.                                                                                                                                                                                                                                                         |
| `attendances`        | Presença = existir a linha. Falta só conta em encontro com chamada feita e para quem já estava na classe.                                                                                                                                                                                                                                                                                                        |

Integridade no banco, não só na aplicação: FKs com `restrict`/`cascade` conforme o caso, `CHECK` para todos os enums, `CHECK` que proíbe liberar em "Para hoje" um bloco do professor e que exige arquivo ou URL nos materiais (exceto referências).

**JSON/JSONB não foi usado**: tudo que existe hoje é claramente relacional.

### Referências bíblicas

A referência continua sendo texto livre (`lessons.bible_reference`, `lesson_readings.reference`): é o que o professor escreve e o que a tela mostra. O texto do trecho é resolvido **na leitura**, por `App\Support\Bible\Reference::parse()` (entende "Lc 5.12-16", "Sl 23", "Gn 1.1-2.3", "Mt 5.3-12; 6.9-13", "Jo 3.16,18", livros numerados e nomes sem acento) e `Bible::passage()`, que consulta `bible_verses` por trecho, na ordem escrita. Referência não reconhecida ou texto não importado devolvem `null`, e os Resources entregam `passage: null`: nada quebra, só não aparece o texto. Não há cache: é uma consulta por trecho em índice único, mais barata do que invalidar cache a cada reimportação.

Não guardamos o texto na lição de propósito: trocar a versão bíblica (reimportar) atualiza todas as lições de uma vez, e a referência escrita nunca diverge do texto exibido.

**Soft delete só em `lessons`**: lições são o acervo de longo prazo. Ao excluir, os domingos planejados ficam livres e os encontros já realizados continuam como histórico.

### Lição x domingo (encontros)

A revista tem ~18 lições, mas há domingos sem EBD e lições que ocupam dois domingos porque a conversa rendeu. Por isso a data **não** é da lição: é do encontro.

- **Domingos da classe** (`/admin/classes/{classe}/domingos`): "Planejar trimestre" cria um domingo por semana e distribui as lições da série pela numeração da revista; "Sem EBD" cancela o domingo e (opcionalmente) empurra as lições, e pode ser desfeito ("Voltar a ter EBD"); "Continua no próximo" repete a lição no domingo seguinte e empurra as demais (`ShiftPlannedLessons`). Se uma lição sobra no fim, o professor é avisado. Cada domingo tem página própria (`/domingos/{id}`) com a lição do dia, a chamada (feita ou corrigida a qualquer momento depois do dia, inclusive em domingo sem lição) e "onde paramos".
- **Lição da semana** (`CurrentLessonQuery`): a do próximo encontro não cancelado (hoje incluído). Mostra "encontro 2 de 2", avisa domingos sem EBD no caminho e, sem encontros futuros, cai para o último realizado. Lição ainda em rascunho aparece como "em preparação".
- `lessons.scheduled_for` é mantido por `SyncLessonSchedule` (primeiro encontro ativo) só para a biblioteca e listagens.

### Ciclo de vida da lição

```
draft ──publicar──▶ published
  ▲                    │
  └────despublicar─────┘
```

"Concluída" deixou de ser status: vem dos encontros realizados. Publicar não exige data. A primeira publicação dispara `LessonPublished`, que avisa a classe por notificação (`NotifyClassroomOfLessonPublished`).

A migração `2026_09_23_001000_backfill_meetings_and_simplify_lesson_status` converteu os dados antigos: cada lição com data virou um encontro (realizado se estava concluída ou já passou), `completed` virou `published` e as notas do professor viraram um bloco `teacher_note`.

## Acesso público x privado

O objetivo é: **link do WhatsApp → conteúdo**, sem tela de login no meio.

| Situação                            | Visitante               | Membro da classe | Professor da classe / admin |
| ----------------------------------- | ----------------------- | ---------------- | --------------------------- |
| Rascunho                            | 404                     | 404              | ✅                          |
| Publicada, **pública**              | ✅                      | ✅               | ✅                          |
| Publicada, **só membros**           | redireciona para login  | ✅               | ✅                          |
| Blocos e materiais do professor     | ❌ (nem são carregados) | ❌               | ✅                          |
| Leitura e anotação (dados pessoais) | —                       | só os próprios   | só os próprios              |
| Progresso e presença de um aluno    | ❌                      | só os próprios   | ✅ (nunca as anotações)     |

- A regra vive em `LessonPolicy::view()` e é espelhada no escopo `Lesson::visibleTo()` para listas (home, biblioteca).
- Conteúdo do professor é filtrado **na consulta** (`LessonController::present`) com `LessonPolicy::viewTeacherContent`; arquivos do professor dão 404 para os demais em `MaterialFileController`.
- Para usuário logado sem acesso respondemos 404, não 403, para não confirmar que um conteúdo restrito existe.

## Slugs e URLs

URL pública: `/licoes/{slug}`, curta e boa para WhatsApp.

- Slug **global** (único na tabela), gerado do título.
- Colisão: primeiro tenta `titulo-{classe}` (`a-santidade-de-deus-adultos`), depois sufixo numérico.
- Lições excluídas continuam reservando o slug, para um link antigo nunca apontar para outro conteúdo.
- O slug pode ser editado **só enquanto a lição é rascunho**. Depois de publicada, o link pode ter sido compartilhado e fica congelado.

_Trade-off:_ não usamos `/classes/{classe}/licoes/{slug}` para manter a URL curta. Se um dia for preciso renomear links publicados, o caminho é uma tabela de redirecionamentos (`lesson_slug_redirects`).

## Busca da biblioteca

PostgreSQL full-text, sem Elasticsearch:

- configuração `ebd_portuguese` = `portuguese` + `unaccent` ("licao" encontra "lição", "santidade" encontra "santidades");
- coluna `lessons.search_vector` **gerada** (`GENERATED ALWAYS … STORED`) com pesos: título e texto bíblico (A), resumo, versículo-chave e alvo (B), estudo principal (C) e blocos de aluno (D), com índice GIN;
- como coluna gerada não lê outras tabelas, o texto dos blocos de **aluno** é copiado para `lessons.blocks_text` por `SyncLessonSearchText`. Blocos do professor nunca entram: o trecho destacado os revelaria;
- o título da série também é pesquisado (join);
- a entrada do usuário é convertida em termos-prefixo (`sant:*`), apenas letras e números, sempre via binding: não há como injetar sintaxe de `tsquery`;
- o trecho destacado (`ts_headline`) é escapado no servidor e só então recebe `<mark>`.

_Limitações conhecidas:_ abreviações bíblicas ("Lc 5") não são expandidas; títulos de materiais não entram no índice.

## Conteúdo em Markdown

O professor escreve o estudo em Markdown. A conversão é no servidor (`App\Support\Markdown`) com HTML bruto removido e links inseguros bloqueados, então o front pode renderizar o HTML com segurança. Títulos `##`/`###` viram os **tópicos do Modo Domingo**.

_Trade-off:_ um editor visual (rich text) seria mais amigável para alguns professores, mas traria sanitização de HTML, mais dependências e mais superfície de XSS. Markdown com dicas no formulário é suficiente para começar.

## Modo Domingo

Página própria (`lessons/sunday`), sem a navegação do app: título, texto base, versículo-chave e tópicos. Fonte ajustável guardada no `localStorage`. Alunos também podem abrir.

Para quem conduz a classe aparecem ainda: **chamada** (toque nos nomes; a lista inteira é reenviada a cada mudança, então Wi-Fi ruim não duplica nada), o **roteiro**, as **notas de precisão**, o "**se houver tempo**" recolhido e o botão **Encerrar aula** (registra onde a turma parou e se a lição terminou ou continua no próximo domingo). O encontro conduzido é o de hoje; senão o último sem chamada; senão o próximo (`MeetingForLessonQuery`).

Ficou de fora de propósito: sincronizar a tela do professor com os alunos em tempo real e modo apresentação/projetor.

## Semana de estudo do aluno

- **Semana de leitura**: o plano de uma lição vai da segunda-feira até o domingo do encontro (`ChurchCalendar::readingWeekday`, `Lesson::readingWeekdayToday`). Fora dessa semana não há "leitura de hoje": no domingo, a leitura de domingo da lição seguinte só vale no domingo dela. A mesma regra vale para a página da lição, o Início, "Leituras da semana" e os lembretes.
- **Semana de estudo** (`StudyWeekQuery`): semana de segunda a domingo da lição atual, com a leitura de cada dia; qualquer dia pode ser marcado como lido a qualquer momento (adiantar ou ler tudo no fim de semana), e o check-in guarda o dia do plano (`weekday`) e a data em que foi marcado (`read_on`, do servidor), curiosidades e conceitos liberados um por dia (`drip_weekday` ou distribuição automática), progresso e sequência de dias. Fica no **Início** (leitura de hoje marcável, faixa da semana, "Para hoje" e atalhos), que reaproveita a lição atual já calculada; **Leituras da semana** (`/minha-semana`) é só a lista dos dias. Login pelo link, cadastro completo e lembretes levam ao Início.
- **Selos** (`AwardBadges`), concedidos no momento da ação, sem scheduler: semana completa (leituras de segunda a sábado de uma lição), 7 e 30 dias seguidos, leitor fiel e presença em todos os domingos do trimestre. São pessoais: **não há ranking**.
- **Sequência** (`StudyStreak`): dias (`read_on`) em que marcou leitura ou teve presença no domingo; continua viva se o último dia foi ontem.

## Gestão da classe

A gestão é organizada em torno da classe (`/admin/classes/{classe}`), com abas **Resumo · Domingos · Alunos · Relatório**. O Painel (`/admin`) mostra cada classe com o que vem neste domingo e os domingos pendentes; professores veem só as próprias classes (com uma só, "Classes" vai direto para ela). Quem dá aula na classe é definido pela administração, na edição da classe (seção Professores).

- **Resumo** (`ClassroomOverviewQuery`, também usado pela ferramenta `get_classroom_overview` do MCP): este domingo ("Hoje" ou "Próximo domingo", com Modo Domingo, chamada e mensagem da semana), último domingo com "onde paramos", domingos pendentes, alunos que precisam de atenção (com WhatsApp), aniversariantes do mês e os números da classe.
- **Alunos** (`/alunos`): a lista com a frequência do período e os avisos (precisa de atenção, novo, aniversário, cadastro pendente, nunca entrou). "Adicionar aluno" avisa quando já existe nome parecido na classe (`FindSimilarStudents`, o mesmo do MCP).
- **Ficha do aluno** (`/alunos/{id}`): contato e WhatsApp, idade e gênero, frequência, estudo em casa por lição, domingo a domingo desde que entrou, **anotações do professor** (`student_notes`: só professores da classe e a administração veem; quem escreveu ou a administração edita), acesso ao app (cadastro, link, aparelhos com lembrete), mover de classe e remover. O professor corrige nome, WhatsApp, nascimento e gênero de qualquer aluno (`UpdateStudentProfile`); o e-mail (login) só em conta sem senha ou pela administração.
- **Relatório** (`/relatorio`): fechado por série (revista). Padrão: a série da lição da semana; senão a que está em andamento; senão a última; sem série, os últimos 3 meses. Mostra a chamada aluno × domingo (em branco antes de o aluno entrar), totais por domingo e visitantes, frequência por gênero e estudo em casa por lição; imprime em A4 deitado.
- **Vocabulário:** Domingo (Planejado, Realizado, Sem EBD); Chamada (o ato e a lista); Presentes (contagem); Frequência (taxa); Estudo em casa; Onde paramos (a anotação do domingo); Alunos (nunca "Membros").

### Uma regra para cada número

Tudo sai do **livro de chamada** (`AttendanceBookQuery` → `AttendanceBook`) e de `HomeStudyQuery`, calculado na leitura, sem tabelas de agregação: o volume de uma classe de EBD é pequeno.

- **Início do aluno na classe** (`Enrollment::sinceMap`): o menor entre o dia seguinte à entrada (no fuso da igreja) e a primeira presença na classe. Quem é cadastrado durante a aula de domingo só conta naquele domingo se estiver na chamada; uma chamada corrigida depois antecipa o início.
- **Frequência do aluno:** presenças ÷ domingos com chamada desde o início. **Frequência da classe:** Σ presentes ÷ Σ esperados. Visitantes ficam fora da taxa. Domingos sem chamada ou sem EBD não entram.
- **Faltas seguidas:** domingos com chamada mais recentes sem presença, só a partir do início do aluno.
- **Estudo em casa (lição):** alunos que contavam no domingo da lição e marcaram alguma leitura ÷ esses alunos; média de dias = dias marcados ÷ leitores.
- **Precisa de atenção** (`StudentsNeedingAttention`): `EBD_RISK_MISSED_MEETINGS` faltas seguidas ou `EBD_RISK_INACTIVE_DAYS` dias sem leitura nas lições da classe. Quem entrou há menos de 14 dias fica de fora, e quem já leu o plano inteiro da lição atual não conta como "sem leitura" (uma lição de dois domingos tem uma semana só de leituras).
- **Pendência:** domingo passado que continua "Planejado".
- **Período** (`Period`): a série da lição da semana (datas da série ou, sem elas, do primeiro ao último domingo das lições dela); sem série, os últimos 3 meses.

Só os alunos atuais entram nos números. Quem sai da classe continua na chamada dos domingos em que esteve (a chamada só sincroniza a lista dos alunos atuais), mas some das contas; ver "Débitos".

## Autenticação e autorização

- **Fortify** (headless, oficial) via starter kit React: login, logout, cadastro opcional, "esqueci minha senha" e confirmação de senha. 2FA, passkeys e verificação de e-mail foram removidos para reduzir atrito.
- **Link pessoal do aluno** (`/entrar#token`): o professor cadastra o aluno só com nome (e WhatsApp) e envia o link. Decisões:
    - o token (48 caracteres aleatórios) vai no **fragmento** da URL: não chega ao servidor no GET, não aparece em logs e o preview do WhatsApp não consegue entrar. A página lê o fragmento, apaga-o da barra de endereços e envia por POST (com CSRF);
    - o banco guarda só o `sha256`; o link em claro aparece uma vez, no flash da resposta;
    - login com "lembrar de mim" longo (`EBD_ACCESS_LINK_REMEMBER_DAYS`) e `session()->regenerate()`. Conta sem senha devolve `''` em `getAuthPassword()`: o Laravel recusa o cookie "lembrar de mim" quando a senha não é string, e o aluno perdia o acesso quando a sessão (2h) expirava;
    - gerar outro link revoga o anterior; "Bloquear acesso" revoga, troca o `remember_token` e apaga as sessões (`SESSION_DRIVER=database`), e só vale para alunos que não são professores nem admin; remover o aluno da classe revoga o link dela;
    - links **nunca** autenticam professores ou administradores; promover a professor revoga os links;
    - rate limit por IP (10/min e 50/dia) e mensagem de erro genérica.
- **Completar cadastro** (`/completar-cadastro`, middleware `EnsureProfileIsComplete`): aluno (que não é professor nem admin) sem senha, e-mail, WhatsApp, nascimento ou gênero é levado a esta tela antes de usar o app; depois volta para a página que pediu. Ficam liberados só sair, o próprio link e as inscrições de push. Ao criar a senha o link é revogado e o login é refeito com "lembrar de mim" (o cookie guarda um resumo da senha). `EBD_ONBOARDING_REQUIRED=false` desliga a exigência em emergência.
- **Recuperar a senha:** sem provedor de e-mail em produção (`EBD_PASSWORD_RESET_BY_EMAIL=false`), "Esqueci minha senha" orienta a pedir um link novo ao professor. O professor da classe gera link para qualquer aluno, inclusive quem já tem senha; entrar por ele **apaga a senha antiga** e leva a criar outra em "Completar cadastro". Quem gerou fica em `access_links.created_by`, e a pessoa percebe porque precisa criar senha nova.
- **Perfil** (`/conta`): foto, classes e papel em cada uma, "Meus dados" (nome, e-mail, WhatsApp, nascimento e gênero), senha e aparência numa página só. A tela de senha abre sem pedir confirmação: trocar a senha já exige a senha atual. E-mails são guardados em minúsculas (é assim que o login e o "adicionar por e-mail" procuram).
- Rate limit: login (Fortify, 5/min), link pessoal, estudo do aluno (60/min), arquivos (60/min) e biblioteca (90/min).

## Agentes de IA (MCP)

Servidor MCP com o pacote oficial `laravel/mcp` (`app/Mcp`, rota em `routes/ai.php`), autenticado por OAuth com o Passport.

- **Age como a pessoa.** O token é de um usuário; cada ferramenta (base `App\Mcp\Tools\EbdTool`) chama as mesmas Policies das telas (`Gate::forUser`) e só enxerga as classes de `manageableClassroomIds()`. Aluno não vê nenhuma ferramenta, e a rota exige `can:access-admin`.
- **Mesmas regras, mesmas Actions.** As regras de validação saíram dos Form Requests para traits em `app/Concerns` (`LessonValidationRules`, `MeetingValidationRules`, `StudentValidationRules`) e são usadas pelas telas, pelo `lesson:import` e pelo MCP. O trabalho é sempre das Actions (`ImportLessonDraft`, `SaveLessonBlock`, `RecordAttendance`...).
- **Nomes, não ids.** O agente conversa com a pessoa: classes são achadas por slug ou nome, alunos por nome sem acento. Quando o nome é ambíguo, o erro lista os candidatos com id para o agente perguntar.
- **Só rascunho.** Nenhuma ferramenta publica nem edita lição publicada (há um teste que procura `ChangeLessonStatus` em `app/Mcp`). Materiais com arquivo continuam só no app.
- **Chamada sem surpresa.** `record_attendance` exige `mode`: `add` soma aos presentes, `set` troca a lista (a Action sincroniza a lista inteira). A resposta traz presentes e ausentes para a pessoa conferir.
- **Link de acesso não passa pelo agente.** O link em claro só existe uma vez, na tela de Alunos; gerar outro revogaria o anterior sem ninguém ver o novo. Por isso o MCP cadastra o aluno sem link.
- **Auditoria.** `audit_logs` guarda quem, por qual aplicativo (cliente OAuth), a ferramenta, os argumentos (textos longos viram tamanho + hash), o antes e o depois, e as tentativas negadas. Serve para conferir e desfazer; o app não lê a tabela.
- **Tela de consentimento em Blade** (`resources/views/mcp/authorize.blade.php`). Como o Fortify devolve para `/oauth/authorize` numa navegação do Inertia, o middleware `RequireFullPageVisit` transforma essa volta em carregamento de página inteira.

## Frontend

- Starter kit oficial (Inertia + React + TS + Tailwind + primitivos shadcn/Radix). Não há painel administrativo pronto (Filament/Nova): ele imporia visual de "sistema administrativo", e a gestão aqui é pequena e precisa ser boa no celular.
- Tipografia: Literata (leitura) + Inter (interface), **auto-hospedadas** via `@fontsource` (sem CDN, bom para privacidade e PWA).
- Duas cascas: o **app de estudo** (`AppLayout`: Início, Biblioteca, Perfil) e a **gestão** (`AdminLayout`, páginas `admin/*`: Painel, Classes, Lições, Séries), cada uma com o próprio topo e menu inferior. Professores e administradores entram na gestão pelo botão "Gestão" do topo e voltam por "Voltar ao app"; a semana de estudo vale para qualquer membro de classe, professor ou aluno (`auth.user.is_member`).
- Mobile-first: navegação inferior no celular, alvos de toque de 44px, `<select>` nativo, seções âncora, compartilhamento pelo menu nativo (Web Share API).
- Dark mode aproveitando o mecanismo do starter kit.
- Wayfinder gera funções tipadas para as rotas do Laravel: renomear uma rota quebra o `tsc`, não a produção.

## PWA

`public/manifest.webmanifest`, ícones (incluindo maskable) e `public/sw.js`, registrado só no build de produção.

O ícone (marcador de página com a cruz vazada) é vetorial: `public/icons/icon.svg` é a arte de fundo inteiro (o sistema arredonda), `public/favicon.svg` é um recorte mais justo com cantos arredondados. Os PNGs (`icon-192/512`, `maskable-512`, `apple-touch-icon`, `favicon.ico` 16/32/48) são exportados desses SVGs; o marcador já cabe no círculo de 80% do maskable. `badge-96.png` é a silhueta branca que o Android mostra na barra de status das notificações. O logo dentro do app e a tela de abertura usam o próprio `icon.svg`. Ao trocar os ícones, suba a `VERSION` do `sw.js`: ele guarda `/icons/*` em cache.

Tela de abertura (`resources/views/partials/splash.blade.php` + `resources/js/lib/splash.ts`): cobre a página enquanto o JavaScript carrega, com um versículo curto sorteado de `App\Support\SplashVerse` (só as referências ficam no código; o texto vem da Bíblia importada, e sem ela aparece uma mensagem). Na primeira abertura da sessão fica ~2s para dar tempo de ler (tocar dispensa); nas recargas seguintes só aparece se o carregamento passar de 400ms.

O service worker faz cache apenas de assets versionados (`/build/*`) e ícones. Páginas HTML **não** são guardadas: sempre vêm da rede e, sem conexão, aparece `offline.html`. Isso evita mostrar conteúdo desatualizado ou dados de uma sessão logada. Leitura offline de lições fica para uma etapa futura.

## Docker

Uma imagem de desenvolvimento (PHP 8.5 CLI + Composer + Node) usada por `app`, `vite` e `worker`. O Node fica na mesma imagem porque o plugin do Wayfinder executa `php artisan` durante o Vite.

A aplicação roda com `php artisan serve` (com `PHP_CLI_SERVER_WORKERS`). É adequado para desenvolvimento e **não** é a recomendação para produção, onde o caminho natural é Nginx + PHP-FPM, FrankenPHP/Octane ou uma plataforma gerenciada (Laravel Cloud, Forge).

## Débitos e próximos passos conhecidos

- Não há Content-Security-Policy (exige ajuste para o Vite em dev e embeds do YouTube).
- Sem imagem/Dockerfile de produção nem pipeline de deploy.
- Upload de áudio grande passa pela aplicação; em produção com S3/R2 o ideal é upload direto com URL pré-assinada.
- `Content-Range` não é suportado no stream local (áudio longo não permite "pular" no disco local; no S3 funciona).
- Busca não indexa títulos de materiais.
- A "mensagem da semana" (Resumo da classe) é copiada e colada no grupo do WhatsApp: não há envio automático para grupos.
- Não há importação automática dos PDFs do NotebookLM para blocos: o conteúdo é colado em Markdown (os PDFs podem ser anexados como material, marcando "só professor" quando for o caso).
- Aluno removido da classe mantém seus registros, mas sai das listas e dos denominadores.
- Colisões de data na migração antiga (duas lições da mesma classe no mesmo dia) ficaram só com uma no encontro; a outra aparece sem data e é ajustada em Domingos.

## Notificações push

Web Push do PWA, sem serviço de terceiros: o servidor assina cada envio com as chaves VAPID e fala direto com o serviço de push do navegador (Google, Apple, Mozilla). Biblioteca `minishlink/web-push`.

- **Regra sempre por classe.** Quem decide os destinatários são as Actions em `app/Actions/Notifications`: `SendReadingReminders` (9h e 20h), `SendLessonReminder` (sábado, se há encontro amanhã) e `NotifyLessonPublished` (listener de `LessonPublished`). Todas partem de `Classroom::active()` e dos membros com aparelho inscrito.
- **Mesma lógica das telas.** A "leitura de hoje" do lembrete é a do Início (`CurrentLessonQuery` + plano de leitura; sem plano, reler o texto base de segunda a sábado). Rascunhos nunca geram lembrete, mesmo que o primeiro destinatário seja professor.
- **Envio** por trás da interface `PushSender`: `WebPushSender` em produção, `NullPushSender` sem chaves e `FakePushSender` nos testes. Um `flush` por classe manda as requisições em paralelo; 404/410 apaga a inscrição.
- **Agendamento** em `routes/console.php`, no fuso da igreja, com `onOneServer` e `withoutOverlapping`. Em produção roda no serviço `scheduler` (`schedule:work`); o `app` não agenda nada.
- **Falha nunca bloqueia.** O listener da publicação captura qualquer exceção e só reporta: publicar a lição não depende do push.
