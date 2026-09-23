# EBD — Escola Bíblica Dominical

O lugar permanente e organizado dos estudos da EBD. O WhatsApp continua sendo o canal de aviso; o conteúdo (lições, PDFs, leituras, perguntas, vídeos, referências) fica aqui, fácil de achar hoje e daqui a alguns anos.

O fluxo do produto:

**Próxima aula → preparação durante a semana → aula no domingo → biblioteca**

- **Início**: responde "o que eu preciso estudar para o próximo domingo?" (contagem regressiva, texto base, leitura do dia, materiais e perguntas).
- **Lição** (`/licoes/{slug}`): página de leitura pensada para o celular, com link bom para compartilhar. Lições públicas abrem **sem login**.
- **Modo Domingo** (`/licoes/{slug}/domingo`): visual limpo para conduzir ou acompanhar a aula, com tópicos, perguntas e notas do professor.
- **Biblioteca** (`/biblioteca`): busca por título, conteúdo, série e texto bíblico, com filtros por classe, série e ano.
- **Gestão** (`/admin`): professores criam séries e lições, adicionam leituras, materiais e perguntas, ordenam, publicam e concluem.

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

O seed cria as classes **Jovens** e **Adultos**, a série **Jornada dos Milagres de Jesus** com a lição **A Santidade de Deus** (Lucas 5:1–11) no próximo domingo, aulas anteriores, uma série do ano passado, uma lição restrita a membros e um rascunho. As datas são calculadas a partir de hoje, então a home sempre tem uma "próxima aula".

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

## API e mobile no futuro

A regra de negócio não depende do Inertia: Actions, Policies, Form Requests, Queries e API Resources são reaproveitáveis. Para expor uma API REST (app Flutter/React Native):

1. `php artisan install:api` (instala Sanctum e cria `routes/api.php`);
2. controllers em `app/Http/Controllers/Api/V1` chamando as mesmas Actions e devolvendo os mesmos Resources;
3. autenticação por token Sanctum para o app; o site continua com sessão.

Mais detalhes em [`docs/arquitetura.md`](docs/arquitetura.md).
