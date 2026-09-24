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
- **Queries** (`app/Queries`) para leituras com regra: `NextLessonQuery` (home) e `LibrarySearch` (busca).
- **Resources** (`app/Http/Resources`) definem o formato dos dados enviados às páginas. São os mesmos que uma API REST devolveria.
- **Sem repository pattern**: o Eloquent já é a camada de persistência; escopos (`Lesson::visibleTo()`, `upcoming()`) cobrem a reutilização de consultas.
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

| Tabela             | Observações                                                                                                                                                                                                                                                           |
| ------------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `users`            | `is_admin` para a administração geral. `avatar_path`: foto de perfil no disco `public` (o app recorta e reduz para 512px antes de enviar). `email` e `password` são opcionais: aluno criado pelo professor entra pelo link pessoal. `phone` monta o link do WhatsApp. |
| `classroom_user`   | Papel **por classe** (`teacher`/`student`), com `unique(classroom_id, user_id)`. `created_at` é a data de entrada na classe (usada nos indicadores de presença).                                                                                                      |
| `classrooms`       | Classe (Jovens, Adultos…). `Classroom` porque `Class` é palavra reservada.                                                                                                                                                                                            |
| `series`           | A revista/trimestre de uma classe. Slug único por classe.                                                                                                                                                                                                             |
| `lessons`          | Espelha a lição da revista: `number` (único por série), título, texto base, `key_verse`, `goal` e o estudo principal em Markdown (`content`). `status` é só editorial. `scheduled_for` é **cache** (ver abaixo).                                                      |
| `class_meetings`   | **Encontros** da classe: `held_on`, `lesson_id` (opcional), `status` (`planned`/`held`/`cancelled`), `title`, `notes` (só professor), `attendance_taken_at`, `visitors_count`. Único por classe e dia.                                                                |
| `lesson_blocks`    | Blocos além do estudo principal: `kind` (roteiro, se houver tempo, nota de precisão, notas, contexto, teologia, curiosidade, aplicação, conceito), `audience` (`teacher`/`student`), Markdown e `drip_weekday`.                                                       |
| `lesson_materials` | Uma tabela para todos os tipos (`pdf`, `file`, `link`, `video`, `audio`, `reference`). `audience` separa material só do professor (ex.: manual completo do NotebookLM).                                                                                               |
| `lesson_readings`  | Leituras da semana; `weekday` ISO (1 = segunda … 7 = domingo) ou nulo.                                                                                                                                                                                                |
| `access_links`     | Links pessoais de acesso. Só o hash sha256 do token; no máximo um ativo por pessoa (índice único parcial); contador de uso.                                                                                                                                           |
| `reading_checkins` | Leitura marcada: uma por pessoa, lição e dia do plano (`weekday`), marcável a qualquer momento. `read_on` guarda quando a pessoa marcou (fuso da igreja).                                                                                                             |
| `lesson_notes`     | Anotação pessoal. **Privada**: não existe rota nem prop que a entregue a outra pessoa.                                                                                                                                                                                |
| `user_badges`      | Selos pessoais; os "por trimestre" guardam `series_id`. Único por pessoa, selo e série.                                                                                                                                                                               |
| `attendances`      | Presença = existir a linha. Falta só conta em encontro com chamada feita e para quem já estava na classe.                                                                                                                                                             |

Integridade no banco, não só na aplicação: FKs com `restrict`/`cascade` conforme o caso, `CHECK` para todos os enums, `CHECK` que proíbe liberar em "Minha semana" um bloco do professor e que exige arquivo ou URL nos materiais (exceto referências).

**JSON/JSONB não foi usado**: tudo que existe hoje é claramente relacional.

**Soft delete só em `lessons`**: lições são o acervo de longo prazo. Ao excluir, os domingos planejados ficam livres e os encontros já realizados continuam como histórico.

### Lição x domingo (encontros)

A revista tem ~18 lições, mas há domingos sem EBD e lições que ocupam dois domingos porque a conversa rendeu. Por isso a data **não** é da lição: é do encontro.

- **Agenda da classe** (`/admin/classes/{classe}/agenda`): "Planejar trimestre" cria um encontro por domingo e distribui as lições da série pela numeração da revista; "Sem EBD" cancela o domingo e (opcionalmente) empurra as lições; "Continua no próximo" repete a lição no domingo seguinte e empurra as demais (`ShiftPlannedLessons`). Se uma lição sobra no fim, o professor é avisado.
- **Lição da semana** (`CurrentLessonQuery`): a do próximo encontro não cancelado (hoje incluído). Mostra "encontro 2 de 2", avisa domingos sem EBD no caminho e, sem encontros futuros, cai para o último realizado. Lição ainda em rascunho aparece como "em preparação".
- `lessons.scheduled_for` é mantido por `SyncLessonSchedule` (primeiro encontro ativo) só para a biblioteca e listagens.

### Ciclo de vida da lição

```
draft ──publicar──▶ published
  ▲                    │
  └────despublicar─────┘
```

"Concluída" deixou de ser status: vem dos encontros realizados. Publicar não exige data. A primeira publicação dispara `LessonPublished` (ainda sem listeners: é o gancho para notificações).

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

- **Minha semana** (`/minha-semana`, `StudyWeekQuery`): semana de segunda a domingo da lição atual, com a leitura de cada dia; qualquer dia pode ser marcado como lido a qualquer momento (adiantar ou ler tudo no fim de semana), e o check-in guarda o dia do plano (`weekday`) e a data em que foi marcado (`read_on`, do servidor), curiosidades e conceitos liberados um por dia (`drip_weekday` ou distribuição automática), checklist "Prepare-se para domingo" e sequência de dias.
- **Selos** (`AwardBadges`), concedidos no momento da ação, sem scheduler: semana completa (leituras de segunda a sábado de uma lição), 7 e 30 dias seguidos, leitor fiel e presença em todos os domingos do trimestre. São pessoais: **não há ranking**.
- **Sequência** (`StudyStreak`): dias (`read_on`) em que marcou leitura ou teve presença no domingo; continua viva se o último dia foi ontem.

## Evolução da classe

`ClassroomInsightsQuery` alimenta o painel `/admin/classes/{classe}/evolucao`: presença por domingo, estudo em casa por lição e **alunos que precisam de atenção** (`EBD_RISK_MISSED_MEETINGS` faltas seguidas ou `EBD_RISK_INACTIVE_DAYS` dias sem leitura; quem entrou há menos de 14 dias fica de fora). Os denominadores respeitam a data de entrada do aluno na classe. `SeriesReportQuery` gera o relatório do trimestre, pensado para impressão. Tudo é calculado na leitura, sem tabelas de agregação: o volume de uma classe de EBD é pequeno.

## Autenticação e autorização

- **Fortify** (headless, oficial) via starter kit React: login, logout, cadastro opcional, "esqueci minha senha" e confirmação de senha. 2FA, passkeys e verificação de e-mail foram removidos para reduzir atrito.
- **Link pessoal do aluno** (`/entrar#token`): o professor cadastra o aluno só com nome (e WhatsApp) e envia o link. Decisões:
    - o token (48 caracteres aleatórios) vai no **fragmento** da URL: não chega ao servidor no GET, não aparece em logs e o preview do WhatsApp não consegue entrar. A página lê o fragmento, apaga-o da barra de endereços e envia por POST (com CSRF);
    - o banco guarda só o `sha256`; o link em claro aparece uma vez, no flash da resposta;
    - login com "lembrar de mim" longo (`EBD_ACCESS_LINK_REMEMBER_DAYS`) e `session()->regenerate()`; o link é reutilizável (vários aparelhos) e o contador de uso ajuda a perceber repasse;
    - gerar outro link revoga o anterior; "Bloquear acesso" revoga, troca o `remember_token` e apaga as sessões (`SESSION_DRIVER=database`);
    - links **nunca** autenticam professores ou administradores; promover a professor revoga os links; professor não gera link para conta que já tem senha (daria acesso às anotações dela), só o admin;
    - rate limit por IP (10/min e 50/dia) e mensagem de erro genérica.
- **Perfil** (`/conta`): foto, classes e papel em cada uma, "Meus dados", senha e aparência numa página só. A tela de senha abre sem pedir confirmação: trocar a senha já exige a senha atual. Contas sem senha podem editar o perfil, criar uma senha (depois de cadastrar e-mail) e excluir a conta sem digitar senha.
- Rate limit: login (Fortify, 5/min), link pessoal, estudo do aluno (60/min), arquivos (60/min) e biblioteca (90/min).

## Frontend

- Starter kit oficial (Inertia + React + TS + Tailwind + primitivos shadcn/Radix). Não há painel administrativo pronto (Filament/Nova): ele imporia visual de "sistema administrativo", e a gestão aqui é pequena e precisa ser boa no celular.
- Tipografia: Literata (leitura) + Inter (interface), **auto-hospedadas** via `@fontsource` (sem CDN, bom para privacidade e PWA).
- Mobile-first: navegação inferior no celular, alvos de toque de 44px, `<select>` nativo, seções âncora, compartilhamento pelo menu nativo (Web Share API).
- Dark mode aproveitando o mecanismo do starter kit.
- Wayfinder gera funções tipadas para as rotas do Laravel: renomear uma rota quebra o `tsc`, não a produção.

## PWA

`public/manifest.webmanifest`, ícones (incluindo maskable) e `public/sw.js`, registrado só no build de produção.

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
- Notificações (push/WhatsApp) ainda não existem: a "mensagem da semana" na agenda é copiada e colada no grupo. `LessonPublished` é o gancho.
- Não há importação automática dos PDFs do NotebookLM para blocos: o conteúdo é colado em Markdown (os PDFs podem ser anexados como material, marcando "só professor" quando for o caso).
- Aluno removido da classe mantém seus registros, mas sai das listas e dos denominadores.
- Colisões de data na migração antiga (duas lições da mesma classe no mesmo dia) ficaram só com uma no encontro; a outra aparece sem data e é ajustada pela agenda.
