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

- **Actions** (`app/Actions`) concentram as regras: `CreateLesson`, `UpdateLesson`, `ChangeLessonStatus`, `GenerateLessonSlug`, `StoreLessonMaterial`, `ReorderLessonItems`, `AddClassroomMember`… Uma classe, um método `handle()`, injetável por container. CRUD trivial sem regra (editar pergunta, por exemplo) fica no controller com `$model->update($request->validated())`.
- **Queries** (`app/Queries`) para leituras com regra: `NextLessonQuery` (home) e `LibrarySearch` (busca).
- **Resources** (`app/Http/Resources`) definem o formato dos dados enviados às páginas. São os mesmos que uma API REST devolveria.
- **Sem repository pattern**: o Eloquent já é a camada de persistência; escopos (`Lesson::visibleTo()`, `upcoming()`) cobrem a reutilização de consultas.
- **DTOs** não foram criados: os Form Requests já entregam arrays validados e as Actions documentam o que esperam.

## Modelagem

```
users ──< classroom_user >── classrooms ──< series ──< lessons
  │         (role: teacher|student)            │         │
  └──< lesson_authors >────────────────────────┘         ├──< lesson_materials
                                                         ├──< lesson_questions
                                                         └──< lesson_readings
```

| Tabela             | Observações                                                                                                                                                                                    |
| ------------------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `users`            | `is_admin` para a administração geral. Não existe "papel global" de professor/aluno.                                                                                                           |
| `classroom_user`   | Papel **por classe** (`teacher`/`student`), com `unique(classroom_id, user_id)`. A mesma pessoa pode ser professora em Jovens e aluna em Adultos.                                              |
| `classrooms`       | Classe (Jovens, Adultos…). `Classroom` porque `Class` é palavra reservada.                                                                                                                     |
| `series`           | Pertence a uma classe (cada classe estuda sua revista). Slug único por classe.                                                                                                                 |
| `lessons`          | Pertence a uma classe e, opcionalmente, a uma série da mesma classe (validado na Action). `status`, `visibility`, `published_at`, `completed_at`, soft delete e coluna `search_vector` gerada. |
| `lesson_materials` | Uma tabela para todos os tipos (`pdf`, `file`, `link`, `video`, `audio`, `reference`). A origem é arquivo (`disk` + `path`) **ou** URL. `is_primary` marca o material principal (a revista).   |
| `lesson_questions` | Perguntas para reflexão, ordenadas por `position`.                                                                                                                                             |
| `lesson_readings`  | Leituras da semana; `weekday` ISO (1 = segunda … 7 = domingo) ou nulo.                                                                                                                         |

Integridade no banco, não só na aplicação: FKs com `restrict`/`cascade` conforme o caso, `CHECK` para enums (`status`, `visibility`, `type`, `role`, `weekday`), `CHECK` que impede lição publicada sem data e `CHECK` que exige arquivo ou URL nos materiais (exceto referências).

**JSON/JSONB não foi usado**: tudo que existe hoje é claramente relacional.

**Soft delete só em `lessons`**: lições são o acervo de longo prazo; uma exclusão acidental precisa ser reversível. Materiais, perguntas e leituras são apagados de verdade (o arquivo junto).

### Ciclo de vida da lição

```
draft ──publicar──▶ published ──concluir──▶ completed
  ▲                    │  ▲                     │
  └────despublicar─────┘  └──────reabrir────────┘
```

Regras em `LessonStatus::canTransitionTo()` e `ChangeLessonStatus`. Publicar exige data. A primeira publicação dispara `LessonPublished` (ainda sem listeners: é o gancho para notificar alunos no futuro).

## Acesso público x privado

O objetivo é: **link do WhatsApp → conteúdo**, sem tela de login no meio.

| Situação                  | Visitante              | Membro da classe | Professor da classe / admin |
| ------------------------- | ---------------------- | ---------------- | --------------------------- |
| Rascunho                  | 404                    | 404              | ✅                          |
| Publicada, **pública**    | ✅                     | ✅               | ✅                          |
| Publicada, **só membros** | redireciona para login | ✅               | ✅                          |
| Notas do professor        | ❌ (nem vão nas props) | ❌               | ✅                          |

- A regra vive em `LessonPolicy::view()` e é espelhada no escopo `Lesson::visibleTo()` para listas (home, biblioteca).
- Arquivos seguem exatamente a regra da lição (`MaterialFileController`). Nada fica em `public/`.
- Para usuário logado sem acesso respondemos 404, não 403, para não confirmar que um conteúdo restrito existe.
- O padrão é **pública**: é o que resolve o problema de adoção. "Só membros" existe para conteúdo que a classe prefira não expor.

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
- coluna `lessons.search_vector` **gerada** (`GENERATED ALWAYS … STORED`) com pesos: título e texto bíblico (A), resumo (B), conteúdo (C), com índice GIN;
- o título da série também é pesquisado (join);
- a entrada do usuário é convertida em termos-prefixo (`sant:*`), apenas letras e números, sempre via binding: não há como injetar sintaxe de `tsquery`;
- o trecho destacado (`ts_headline`) é escapado no servidor e só então recebe `<mark>`.

_Limitações conhecidas:_ abreviações bíblicas ("Lc 5") não são expandidas; conteúdo de materiais e perguntas não entra no índice.

## Conteúdo em Markdown

O professor escreve o estudo em Markdown. A conversão é no servidor (`App\Support\Markdown`) com HTML bruto removido e links inseguros bloqueados, então o front pode renderizar o HTML com segurança. Títulos `##`/`###` viram os **tópicos do Modo Domingo**.

_Trade-off:_ um editor visual (rich text) seria mais amigável para alguns professores, mas traria sanitização de HTML, mais dependências e mais superfície de XSS. Markdown com dicas no formulário é suficiente para começar.

## Modo Domingo

Implementado como uma página própria (`lessons/sunday`), sem a navegação do app: título, texto base, tópicos, perguntas (tocáveis para marcar como discutidas, só no aparelho) e notas do professor. Fonte ajustável guardada no `localStorage`. Alunos também podem abrir (sem as notas).

Ficou de fora de propósito: sincronizar a tela do professor com os alunos em tempo real e modo apresentação/projetor.

## Autenticação e autorização

- **Fortify** (headless, oficial) via starter kit React: login, logout, cadastro opcional, "esqueci minha senha" e confirmação de senha. 2FA, passkeys e verificação de e-mail foram removidos para reduzir atrito; todos podem voltar pelo próprio Fortify.
- Cadastro aberto (configurável) cria conta **sem classe**; professor/admin adiciona a pessoa à turma pelo e-mail. Promover a professor é exclusivo do admin.
- Rate limit: login (Fortify, 5/min), arquivos (60/min) e biblioteca (90/min).
- Evolução prevista: login social (Socialite) e magic link entram como novas rotas de autenticação sem mudar o modelo; app mobile usará tokens Sanctum.

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
- Busca não indexa perguntas nem títulos de materiais.
