# EBD — Escola Bíblica Dominical

O lugar permanente e organizado dos estudos da EBD. O WhatsApp continua sendo o canal de aviso; o conteúdo (lições, PDFs, leituras, perguntas, vídeos, referências) fica aqui, fácil de achar hoje e daqui a alguns anos.

O fluxo do produto:

**Agenda da classe → preparação durante a semana → aula no domingo → evolução da classe → biblioteca**

- **Início**: responde "o que eu preciso estudar para o próximo domingo?" (lição do próximo encontro, "encontro 2 de 2", aviso de domingo sem EBD, leitura do dia, materiais e perguntas).
- **Lição** (`/licoes/{slug}`): no formato da revista (número, versículo-chave, alvo, estudo em I/II/III) e além dela: contexto, teologia, curiosidades, conceitos, revisão com gabarito e anotações pessoais. Lições públicas abrem **sem login**.
- **Minha semana** (`/minha-semana`): leitura do dia com "Li hoje", curiosidade do dia, "Prepare-se para domingo", sequência de dias e selos. O aluno entra por um **link pessoal** enviado no WhatsApp, sem senha.
- **Modo Domingo** (`/licoes/{slug}/domingo`): para conduzir ou acompanhar a aula; o professor vê roteiro, notas de precisão, "se houver tempo", faz a chamada e encerra a aula (lição concluída ou continua).
- **Biblioteca** (`/biblioteca`): busca por título, conteúdo, série e texto bíblico, com filtros por classe, série e ano.
- **Gestão** (`/admin`): agenda da classe (planejar trimestre, domingos sem EBD), lições e blocos de aprofundamento, alunos e links de acesso, evolução da classe e relatório do trimestre.

> Decisões de arquitetura, modelagem e trade-offs estão em [`docs/arquitetura.md`](docs/arquitetura.md).

---

## Stack

| Camada                 | Tecnologia                                                                 |
| ---------------------- | -------------------------------------------------------------------------- |
| Backend                | Laravel 13 · PHP 8.5 (Docker/CI; funciona a partir do 8.3)                 |
| Frontend               | Inertia 3 · React 19 · TypeScript · Tailwind CSS 4 · Vite 8 (via Vite+)    |
| Banco                  | PostgreSQL 18 (busca full-text com `unaccent`)                             |
| Cache e filas          | Redis 8                                                                    |
| Autenticação           | Laravel Fortify (starter kit oficial React)                                |
| Rotas tipadas no front | Laravel Wayfinder                                                          |
| E-mail em dev          | Mailpit                                                                    |
| Qualidade              | PHPUnit 12 · Pint · Larastan (nível 7) · Oxlint/Oxfmt (`vp check`) · `tsc` |

---

## 1. Requisitos

- Docker com Docker Compose v2
- `make` (opcional, só atalhos; os comandos equivalentes estão no `Makefile`)
- Git

Não precisa de PHP, Composer, Node ou PostgreSQL instalados na máquina.

## 2. Configurar o `.env`

```bash
cp .env.example .env
```

O `.env.example` já vem pronto para o Docker. Os pontos que você talvez queira mudar:

| Variável                                                              | Para quê                                                                                               |
| --------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------ |
| `EBD_CHURCH_NAME`                                                     | Nome da igreja exibido na interface                                                                    |
| `EBD_TIMEZONE`                                                        | Fuso usado para "hoje", próxima aula e leitura do dia (padrão `Europe/Madrid`)                         |
| `EBD_REGISTRATION_ENABLED`                                            | Liga/desliga o auto-cadastro                                                                           |
| `EBD_MATERIALS_DISK`                                                  | Disco dos arquivos enviados (`local` em dev; `s3` para S3/R2)                                          |
| `EBD_ACCESS_LINK_REMEMBER_DAYS`                                       | Dias que o aparelho do aluno fica conectado após usar o link pessoal (padrão 400)                      |
| `EBD_ACCESS_LINK_TTL_DAYS`                                            | Validade do link pessoal em dias (vazio = até ser trocado ou bloqueado)                                |
| `EBD_RISK_MISSED_MEETINGS`, `EBD_RISK_INACTIVE_DAYS`                  | Limites de "alunos que precisam de atenção" (padrão 2 faltas seguidas / 10 dias sem leitura)           |
| `APP_PORT`, `VITE_PORT`, `FORWARD_DB_PORT`, `FORWARD_MAILPIT_UI_PORT` | Portas publicadas no host                                                                              |
| `DOCKER_UID`, `DOCKER_GID`                                            | Seu usuário no Linux (`id -u`/`id -g`), para os arquivos criados pelo container ficarem com o seu dono |

`DB_HOST`, `REDIS_HOST` e `MAIL_HOST` ficam como `127.0.0.1` no `.env` (útil para rodar comandos fora do Docker); dentro dos containers o `compose.yaml` sobrescreve com os nomes dos serviços.

## 3. Subir tudo pela primeira vez

```bash
make setup
```

O `make setup` cria o `.env` (se não existir), constrói a imagem, instala dependências PHP e Node, gera a `APP_KEY`, sobe os containers, recria o banco e popula os dados de desenvolvimento.

Depois acesse:

- Aplicação: <http://localhost:8000>
- E-mails capturados (Mailpit): <http://localhost:8025>

Sem `make`, o equivalente é:

```bash
cp .env.example .env
docker compose build
docker compose run --rm --no-deps app composer install
docker compose run --rm --no-deps app npm install
docker compose run --rm --no-deps app php artisan key:generate
docker compose up -d
docker compose exec app php artisan migrate:fresh --seed
```

### Serviços do `compose.yaml`

| Serviço    | O que faz                                                                        |
| ---------- | -------------------------------------------------------------------------------- |
| `app`      | `php artisan serve` na porta 8000 (healthcheck em `/up`)                         |
| `vite`     | servidor do Vite com hot reload (porta 5173)                                     |
| `worker`   | `queue:listen` processando a fila (Redis)                                        |
| `postgres` | PostgreSQL 18 com volume persistente `pgdata`; cria também o banco `ebd_testing` |
| `redis`    | cache e filas                                                                    |
| `mailpit`  | caixa de e-mail falsa para desenvolvimento                                       |

## 4. Instalar dependências

```bash
make install          # composer install + npm install dentro do container
```

## 5. Migrations

```bash
make migrate          # php artisan migrate
```

## 6. Dados de desenvolvimento

```bash
make seed             # só popula
make fresh            # apaga tudo, roda as migrations e popula
```

O seed cria as classes **Jovens** e **Adultos**, a série **Jornada dos Milagres de Jesus** com a lição **A Santidade de Deus** (Lucas 5:1–11) no próximo domingo, a **Lição 11 — É Necessário** (Jo 9) completa no formato da revista (blocos, curiosidades liberadas por dia, revisão com gabarito), aulas anteriores com chamada, uma série do ano passado, uma lição restrita a membros e um rascunho. As datas são calculadas a partir de hoje, então a home sempre tem uma "próxima aula".

**Usuários de desenvolvimento (apenas ambiente local, senha `password` para todos):**

| E-mail                | Perfil                                                              |
| --------------------- | ------------------------------------------------------------------- |
| `admin@ebd.test`      | Administrador (todas as classes, cadastro de classes e professores) |
| `professor@ebd.test`  | Professor da classe Jovens                                          |
| `professora@ebd.test` | Professora da classe Adultos                                        |
| `aluno@ebd.test`      | Aluno da classe Jovens                                              |
| `aluna@ebd.test`      | Aluna da classe Adultos                                             |

> ⚠️ Essas credenciais existem só para desenvolvimento. O `DatabaseSeeder` se recusa a rodar com `APP_ENV=production`.

## 7. Frontend

O serviço `vite` já sobe junto (`make up`) com hot reload. Para gerar o build de produção:

```bash
make build-assets     # npm run build
```

As rotas do Laravel viram funções TypeScript tipadas (Wayfinder) em `resources/js/routes` e `resources/js/actions`. Esses arquivos são gerados pelo plugin do Vite e ficam fora do git.

## 8. Testes e qualidade

```bash
make test             # php artisan test (usa o banco ebd_testing do PostgreSQL)
make lint             # Pint --test + vp check (oxlint + oxfmt)
make analyse          # Larastan + tsc
make format           # corrige formatação PHP e front
make check            # tudo o que o CI roda
```

Os testes rodam em **PostgreSQL** de propósito: a busca da biblioteca usa recursos específicos dele.

## 9. Filas

A fila usa Redis e o serviço `worker` já processa os jobs (hoje: e-mail de redefinição de senha).

```bash
make queue                                   # logs do worker
docker compose restart worker                # após mudar código de jobs
docker compose exec app php artisan queue:failed
```

## 10. Derrubar e resetar

```bash
make down             # para os containers (o banco continua no volume)
make reset            # APAGA os volumes (inclusive o banco) e roda o setup de novo
```

---

## Rodar sem Docker (opcional)

Com PHP 8.3+ (extensões `pdo_pgsql`, `intl`, `redis`), Composer, Node 22+, PostgreSQL e Redis locais:

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
# crie os bancos ebd e ebd_testing e ajuste DB_* no .env
php artisan migrate:fresh --seed
composer dev          # servidor + vite + fila + logs
```

---

## Estrutura de pastas

```
app/
  Actions/          regras de negócio (criar lição, publicar, enviar material, slug, reordenar...)
  Enums/            LessonStatus, LessonVisibility, MaterialType, ClassroomRole, Weekday
  Events/           LessonPublished (gancho para notificações futuras)
  Http/
    Controllers/    finos: validam (Form Request), autorizam (Policy), chamam Actions/Queries
    Controllers/Admin/
    Middleware/     props compartilhadas do Inertia, cabeçalhos de segurança
    Requests/       validação
    Resources/      representação JSON usada pelo Inertia (e por uma futura API)
  Models/           Eloquent (+ Concerns/HasPosition para itens ordenáveis)
  Notifications/
  Policies/         quem pode ver/editar o quê
  Queries/          consultas de leitura (próxima aula, biblioteca)
  Support/          ChurchCalendar (fuso da igreja), Markdown seguro
config/ebd.php      configurações do produto
database/           migrations, factories, seeders
docker/             Dockerfile e init do PostgreSQL
resources/js/
  components/       lesson/, admin/, ui/ (primitivos shadcn)
  layouts/          app (navegação), admin, auth, settings
  pages/            uma página Inertia por tela
  types/            tipos espelhando os Resources
tests/Feature, tests/Unit
```

## Como adicionar uma funcionalidade

1. **Modelo/migration** se houver dado novo (`php artisan make:migration`), com FKs, índices e constraints.
2. **Regra de negócio** em uma Action (`app/Actions/...`) — nada de regra dentro de controller ou página React.
3. **Autorização** na Policy do modelo.
4. **Validação** em um Form Request.
5. **Controller** fino chamando a Action e devolvendo `Inertia::render('pasta/pagina', [...])` com Resources.
6. **Página** em `resources/js/pages/...`, usando as rotas geradas pelo Wayfinder (`@/routes/...`).
7. **Teste** de feature cobrindo a regra e a permissão.

## Estratégia de storage

Uploads usam a abstração de filesystem do Laravel com o disco definido em `EBD_MATERIALS_DISK`. Os arquivos ficam **fora da pasta pública**, com nome aleatório, e são entregues por `/materiais/{id}/arquivo` só depois de checar a permissão da lição. Em disco local o Laravel faz o stream; em disco `s3` (AWS S3, Cloudflare R2, MinIO) a aplicação redireciona para uma URL temporária assinada.

Para usar S3/R2:

```bash
composer require league/flysystem-aws-s3-v3 "^3.0"
```

e no `.env`: `EBD_MATERIALS_DISK=s3`, `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION` (`auto` no R2), `AWS_BUCKET`, `AWS_ENDPOINT` (no R2: `https://<account>.r2.cloudflarestorage.com`).

Limites de upload: PDF/arquivos 30 MB, áudio 60 MB (`EBD_MAX_UPLOAD_KB`, `EBD_MAX_AUDIO_KB`). Em produção, `upload_max_filesize`/`post_max_size` do PHP e o limite do proxy (nginx `client_max_body_size`) precisam acompanhar.

## Deploy no Railway

Produção: **https://app-production-4c4c.up.railway.app** (healthcheck em `/up`).

Produção roda no [Railway](https://railway.com), no projeto **EBD** (região `us-east4`), seguindo o mesmo modelo dos outros projetos Laravel da conta: build automático pelo **Railpack**, sem Dockerfile de produção. O `compose.yaml` e o `docker/php/Dockerfile` continuam sendo **só para desenvolvimento local**.

### Arquitetura

```
GitHub (main) ──push──▶ Railway build (Railpack) ──▶ serviço "app" (FrankenPHP)
                                                        │   └─ volume: /app/storage/app (uploads)
                                                        ▼
                                                  serviço "Postgres" (PostgreSQL 18 + volume)
```

| Serviço    | O que é                                                                                                                                                        |
| ---------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `app`      | Laravel servido pelo **FrankenPHP** (Caddy + PHP 8.4), escutando na porta `$PORT` do Railway. Domínio público `*.up.railway.app` com HTTPS do próprio Railway. |
| `Postgres` | PostgreSQL gerenciado do Railway, com volume próprio. Acessado pela rede privada.                                                                              |

Não há Redis, worker nem scheduler, porque o sistema ainda não precisa deles: cache e sessões ficam no PostgreSQL, a fila é `sync` e não existem tarefas agendadas. Se um dia houver jobs pesados, crie um serviço `worker` com o mesmo repositório e o start command `php artisan queue:work --tries=3 --backoff=10 --timeout=90`, e troque para `QUEUE_CONNECTION=database`.

### Como o Railpack builda e inicia

- Detecta Laravel pelo `artisan` e usa a imagem `dunglas/frankenphp` na versão de PHP do `composer.json` (`^8.4`).
- Instala as extensões declaradas como `ext-*` no `composer.json` (`intl`, `pdo_pgsql`) mais as exigidas pelo Laravel.
- Roda `composer install`, `npm ci` e `npm run build` na mesma imagem (o Wayfinder precisa do PHP durante o build do Vite).
- No start, roda `storage:link`, `optimize:clear` e `optimize`. Os caches são refeitos **em runtime**, com as variáveis reais do ambiente, e só então sobe o FrankenPHP.

### Configuração do serviço `app`

| Configuração       | Valor                                                                     |
| ------------------ | ------------------------------------------------------------------------- |
| Source             | GitHub `simonscabello/ebd`, branch `main` (deploy automático a cada push) |
| Builder            | Railpack                                                                  |
| Pre-deploy command | `php artisan ebd:predeploy` (migrations + `ebd:promote-admins`)           |
| Healthcheck        | `/up`, timeout de 300 s                                                   |
| Volume             | montado em `/app/storage/app`                                             |
| Réplicas           | 1 (o volume exige uma única instância)                                    |

### Variáveis de ambiente (serviço `app`)

| Variável                                                        | Valor                                        | Observação                                                                                         |
| --------------------------------------------------------------- | -------------------------------------------- | -------------------------------------------------------------------------------------------------- |
| `APP_NAME`                                                      | `EBD`                                        |                                                                                                    |
| `APP_ENV`                                                       | `production`                                 |                                                                                                    |
| `APP_DEBUG`                                                     | `false`                                      | nunca `true` em produção                                                                           |
| `APP_KEY`                                                       | gerada com `php artisan key:generate --show` | secreta; só no Railway                                                                             |
| `APP_URL`                                                       | `https://${{RAILWAY_PUBLIC_DOMAIN}}`         | referência ao domínio do próprio serviço                                                           |
| `APP_LOCALE` / `APP_FALLBACK_LOCALE`                            | `pt_BR` / `en`                               |                                                                                                    |
| `LOG_CHANNEL` / `LOG_LEVEL`                                     | `stderr` / `info`                            | logs vão para o painel do Railway                                                                  |
| `DB_CONNECTION`                                                 | `pgsql`                                      | também faz o Railpack instalar `pdo_pgsql`                                                         |
| `DB_URL`                                                        | `${{Postgres.DATABASE_URL}}`                 | referência; nenhuma credencial fica no Git                                                         |
| `SESSION_DRIVER` / `SESSION_SECURE_COOKIE` / `SESSION_LIFETIME` | `database` / `true` / `120`                  |                                                                                                    |
| `CACHE_STORE`                                                   | `database`                                   |                                                                                                    |
| `QUEUE_CONNECTION`                                              | `sync`                                       |                                                                                                    |
| `FILESYSTEM_DISK` / `EBD_MATERIALS_DISK`                        | `local` / `local`                            | arquivos no volume                                                                                 |
| `MAIL_MAILER`                                                   | `log`                                        | provisório: e-mails só aparecem no log (ver pendências)                                            |
| `EBD_CHURCH_NAME`, `EBD_TIMEZONE`, `EBD_REGISTRATION_ENABLED`   | ver `.env.example`                           |                                                                                                    |
| `EBD_ACCESS_LINK_*`, `EBD_RISK_*`                               | opcionais, ver `.env.example`                | padrões funcionam; `SESSION_DRIVER` precisa ser `database` para "Bloquear acesso" derrubar sessões |
| `EBD_ADMIN_EMAILS`                                              | e-mails separados por vírgula                | contas promovidas a admin no pre-deploy                                                            |
| `RAILPACK_SKIP_MIGRATIONS`                                      | `true`                                       | as migrations rodam no pre-deploy, não no start                                                    |

### Migrations

Rodam no **pre-deploy command** (`php artisan ebd:predeploy`): um container temporário, com a imagem nova, executa `php artisan migrate --force` **uma vez por deploy**, antes de a versão nova receber tráfego. Se a migration falhar, o deploy é abortado e a versão anterior continua no ar; o erro aparece nos logs do deploy. A migration automática do Railpack no start fica desligada (`RAILPACK_SKIP_MIGRATIONS=true`) para não rodar em paralelo.

Em produção nunca rode `migrate:fresh`, `db:wipe` nem `db:seed`: os seeders são só para desenvolvimento e se recusam a rodar com `APP_ENV=production`.

> **Antes do deploy que separa lição de domingo** (migration `2026_09_23_001000_backfill_meetings_and_simplify_lesson_status`), faça um backup do PostgreSQL (_Postgres → Backups_ no Railway). Ela converte as datas das lições em encontros e remove as colunas `teacher_notes` e `completed_at` (o conteúdo das notas vira um bloco "Notas do professor"). O `down()` existe, mas o backup é a proteção real.

### Primeiro administrador

O banco de produção começa vazio. Para ter o primeiro admin:

1. Crie sua conta normalmente em `/register`.
2. No Railway, defina `EBD_ADMIN_EMAILS` com o seu e-mail.
3. Faça um redeploy. O pre-deploy (`ebd:predeploy`) roda `ebd:promote-admins`, que **só promove contas já existentes** e é idempotente.

Depois disso, crie as classes em **Gestão → Classes** e adicione os professores.

### Storage

Os materiais enviados (PDFs, áudios) ficam no **volume** do serviço `app`, montado em `/app/storage/app`, e continuam existindo entre deploys. Limitações conhecidas:

- Com volume, o serviço roda com **uma réplica**, e cada redeploy tem alguns segundos de indisponibilidade.
- O backup do volume é o do Railway; vale ativar os backups agendados no painel do volume.
- Para escalar ou usar CDN, o código já suporta disco `s3`: crie um Railway Bucket (ou S3/R2), instale `league/flysystem-aws-s3-v3` e defina `EBD_MATERIALS_DISK=s3` com as credenciais `AWS_*`.

### Logs e healthcheck

- **Logs:** aplicação (`LOG_CHANNEL=stderr`), PHP e Caddy escrevem no stdout/stderr e aparecem em _Deployments → Logs_. Falhas de build, de migration (pre-deploy) e de startup aparecem ali.
- **Healthcheck:** `GET /up`, nativo do Laravel. Neste projeto ele também executa `select 1` no PostgreSQL: se o banco não responder, devolve 500 sem detalhes e o Railway não promove o deploy.

### Segurança em produção

- `APP_DEBUG=false`: erros aparecem como página genérica, com detalhes só no log.
- O Laravel confia no proxy do Railway (`trustProxies`), então reconhece HTTPS e o host público. Em produção, todas as URLs são geradas em `https`.
- Cookies de sessão são `secure` e `SameSite=Lax`. O CSRF é o padrão do Laravel.
- `.env` não existe na imagem (as variáveis vêm do Railway), e o Caddy do Railpack esconde `.env*` e `.git`. O cabeçalho `X-Powered-By` é removido.
- Uploads ficam fora de `public/`; os arquivos só saem pelo controller, depois de checar a permissão da lição.

### Deploys futuros

Basta fazer push na `main`. O Railway builda, roda as migrations no pre-deploy, espera o `/up` responder e então troca a versão. Para acompanhar, use o painel do projeto (_Deployments_) ou a CLI (`railway logs`).

### Pendências manuais

- **E-mail:** configure um provedor SMTP (Resend, Postmark, Brevo, etc.) com `MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`. Até lá, a recuperação de senha não chega à caixa de entrada.
- **Domínio próprio:** quando houver, adicione em _Settings → Networking_. O `APP_URL` acompanha automaticamente se continuar usando `${{RAILWAY_PUBLIC_DOMAIN}}`; com domínio customizado, defina a URL explicitamente.

## API e mobile no futuro

A regra de negócio não depende do Inertia: Actions, Policies, Form Requests, Queries e API Resources são reaproveitáveis. Para expor uma API REST (app Flutter/React Native):

1. `php artisan install:api` (instala Sanctum e cria `routes/api.php`);
2. controllers em `app/Http/Controllers/Api/V1` chamando as mesmas Actions e devolvendo os mesmos Resources;
3. autenticação por token Sanctum para o app; o site continua com sessão.

Mais detalhes em [`docs/arquitetura.md`](docs/arquitetura.md).
