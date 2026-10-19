# 08 — UX/UI e acessibilidade (EBD 360)

Auditoria **só pela leitura do código**: nada foi executado e nenhum servidor foi subido. Os contrastes foram calculados a partir dos tokens OKLCH de `resources/css/app.css`, convertidos para sRGB e medidos pela fórmula da WCAG 2.x. Itens com **[OBS]** decorrem do código; itens com **[HIP]** precisam de teste com pessoas (o teste no navegador fica para outro agente).

---

## 1. Arquitetura de componentes e consistência visual

**O que existe**

- Primitivos no estilo shadcn/Radix em `resources/js/components/ui/*` (Button, Input, Dialog, DropdownMenu, Checkbox, Tooltip, NativeSelect) + um `BottomSheet` próprio em cima do Drawer do Flowbite (`resources/js/components/ui/bottom-sheet.tsx:27-178`).
- Blocos de página reutilizados: `Page`, `PageHeader`, `Section`, `EmptyState`, `Breadcrumbs` (`resources/js/components/page.tsx:9-187`).
- Componentes de domínio bem separados: leitura (`components/lesson/*`), semana (`components/week/*`), progresso (`components/progress/*`), gestão (`components/admin/*`).
- Paleta "papel e tinta" com tokens semânticos e tema escuro (`resources/css/app.css:150-213`), e uma ponte para os tokens do Flowbite (`app.css:73-144`).

**Inconsistências observadas**

- [OBS] **Duas bibliotecas de comportamento**: Radix (Dialog, Dropdown, Checkbox, Tooltip) e Flowbite (Drawer do `BottomSheet`, `import 'flowbite'` global em `resources/js/app.tsx:4`). Dois modelos de foco, de ESC e de "inert". O `BottomSheet` reimplementa armadilha de foco à mão (`bottom-sheet.tsx:107-133`) que o Radix Dialog já dá.
- [OBS] **Formulários em dois padrões**: a gestão usa `Field` (liga rótulo, dica e erro com `aria-invalid`/`aria-describedby`, `resources/js/components/form-field.tsx:20-58`) em 12 arquivos; as telas que o **aluno** vê — `auth/complete-profile.tsx`, `auth/login.tsx`, `settings/profile.tsx`, `settings/security.tsx`, `auth/register.tsx`, `auth/reset-password.tsx` — usam `Label` + `Input` + `InputError` soltos, sem ligação do erro ao campo. O público mais frágil ficou com o padrão menos acessível.
- [OBS] **Cores fora do sistema**: `text-red-600` no `InputError` (`resources/js/components/input-error.tsx:13`) em vez de `text-destructive`; `bg-amber-500 text-white` no selo "Rascunho" (`resources/js/pages/lessons/show.tsx:254`, `resources/js/components/admin/lesson-audio-manager.tsx:118`); `emerald-600` na faixa da semana (`resources/js/components/progress/week-bar.tsx:25`) enquanto o resto usa `--success`; `rose-*` no aviso de domingo cancelado (`resources/js/pages/home.tsx:139`); `orange-*` na chama (`resources/js/components/progress/streak-flame.tsx:18-19`).
- [OBS] **Botões "pílula" feitos à mão**: chips de classe no Início (`home.tsx:107`), seletor de classe em Leituras (`resources/js/pages/my-week.tsx:70`), faixa de seções (`resources/js/components/section-nav.tsx:106`) — três implementações do mesmo padrão com alturas diferentes (≈32 px, 36 px, 36 px).
- [OBS] **Cor de progresso do Inertia fixa** em hex (`app.tsx:57`) e `theme-color` em outro hex (`resources/views/app.blade.php:34`), ambos fora dos tokens (não acompanham o tema escuro).

**Propostas**

- Migrar as telas de aluno para `Field` (mudança invisível; ver lista de baixo risco).
- Um componente `ChoiceChip` (altura mínima 44 px) para os três seletores em pílula.
- Médio prazo: decidir entre Radix e Flowbite para painéis; o `BottomSheet` poderia ser um `Dialog` do Radix posicionado embaixo, eliminando o Flowbite do bundle de aluno.

---

## 2. Navegação

- App de estudo: topo (logo, links no computador, "Gestão" para professores, menu da conta) e menu inferior com **3 itens** — Início, Biblioteca, Perfil/Entrar (`resources/js/components/app-navigation.tsx:48-71`, `248-276`). Gestão: 4 itens (`resources/js/layouts/admin-layout.tsx:34-44`).
- O menu inferior tem altura fixa e rótulos `text-[11px]` truncados (`app-navigation.tsx:254`).

**Achados**

- [OBS] "Leituras da semana" e "Meu progresso" **não estão no menu**; só por atalhos do Início (`home.tsx:340-372`) ou em Perfil (`resources/js/pages/settings/index.tsx:72`). O Início é ativo também para `/licoes`, `/minha-semana` e `/meu-progresso` (`app-navigation.tsx:79-85`) — coerente, mas o aluno não tem como "ir para as leituras" pelo menu.
- [OBS] Rótulo do menu inferior em **11 px fixos** (`app-navigation.tsx:254`) e dias da semana em 11 px (`week-bar.tsx:47`). [HIP] Com "texto grande" do sistema, iOS Safari não escala `px`; os rótulos ficam pequenos para idosos justamente no elemento mais usado.
- [OBS] Voltar: links "← Início" com `min-h-9` (36 px) em `show.tsx:208` e `my-week.tsx:38`. Não há botão "voltar" no topo; quem chega pelo WhatsApp não tem histórico no app instalado.
- [OBS] Visitante não logado não tem acesso à ajuda ("Como funciona" só está em Perfil, `settings/index.tsx:161-163`).
- [OBS] O mini player e o botão "Tirar dúvida" flutuam acima do menu inferior (`resources/js/components/lesson/study-helper.tsx:282`, `resources/js/components/lesson/mini-audio-player.tsx:66`). Com os dois visíveis, três camadas fixas ocupam ~170 px da tela do celular. [HIP] Em telas pequenas com fonte grande, sobra pouco para o texto.

**Propostas:** manter 3 itens (simplicidade é um mérito), mas usar rem/`text-xs` no rótulo e testar com fonte grande; botão "Tirar dúvida" recolher para só o ícone (com `aria-label`) depois que a pessoa rola.

---

## 3. Hierarquia e legibilidade de textos longos

**Tipografia**

- Literata (serifada variável) para títulos e estudo; Inter para interface (`app.css:3-5`, `app.css:31-36`).
- Utilitário `reading`: `prose-lg` (18 px), `line-height: 1.75`, títulos internos em sans (`app.css:253-297`).
- Largura de linha: `Page` usa `max-w-2xl` (42 rem = 672 px) no modo leitura (`page.tsx:66-87`); com 18 px e Literata dá ~70–75 caracteres por linha no computador — dentro do recomendado. No celular, a largura é a da tela menos 32 px de margem.
- Modo Domingo: escala do texto em 4 passos, `text-base` a `text-2xl`, lembrada no aparelho (`resources/js/pages/lessons/sunday.tsx:34-47`).

**Achados**

- [OBS] O utilitário `reading` desliga a largura máxima do prose (`max-w-none`, `app.css:254`); a largura depende sempre do contêiner. Em `DailyBlocks` e cartões, a largura é a do cartão (ok). Se alguém usar `reading` dentro de `Page width="wide"` (`max-w-4xl`), a linha passa de 90 caracteres. Hoje isso não acontece nas páginas do aluno.
- [OBS] **Só o Modo Domingo tem controle de tamanho de texto.** A página da lição (`show.tsx`), onde se lê durante a semana, não tem A−/A+.
- [OBS] Muito texto auxiliar em `text-xs` (12 px): rótulo de cada leitura (`resources/js/components/lesson/reading-plan.tsx:169-176`), status da anotação (`resources/js/components/lesson/personal-note.tsx:51-59`), dicas do cadastro (`resources/js/pages/auth/complete-profile.tsx:74-79`, `120-125`, `146-152`), aviso de privacidade do cadastro (`complete-profile.tsx:180-186`), aviso de que a IA pode errar (`study-helper.tsx:173-176`). Para idosos, mensagens de **privacidade e erro** em 12 px são as que mais importam.
- [OBS] A página da lição empilha muita coisa antes do estudo: cabeçalho, texto base, cabeçalho da revista, 2–3 botões, resumo, faixa de seções, leituras (`show.tsx:206-380`). Hierarquia clara, mas longa.
- [OBS] Resposta da IA em `text-[0.95rem]` (`study-helper.tsx:249`), menor que o corpo do estudo (18 px).

**Propostas**

- Controle "Tamanho do texto" global (Perfil), reaproveitando a lógica do Modo Domingo e aplicando no utilitário `reading` (PROPOSTA visual; precisa de validação).
- Subir dicas de privacidade/erro para `text-sm`.

---

## 4. Contraste (tokens de `resources/css/app.css`)

Cálculo OKLCH → sRGB → luminância relativa WCAG. AA exige 4,5:1 para texto normal, 3:1 para texto grande e para componentes/gráficos (1.4.11).

### Tema claro (`app.css:150-180`)

| Par                                                               | Razão   | Resultado          |
| ----------------------------------------------------------------- | ------- | ------------------ |
| `foreground` sobre `background`                                   | 16,5    | AAA                |
| `muted-foreground` sobre `background`                             | 5,7     | AA                 |
| `muted-foreground` sobre `card`                                   | 6,0     | AA                 |
| `muted-foreground` sobre `muted` (chips, faixa de seções inativa) | 5,3     | AA                 |
| `muted-foreground` sobre `accent`                                 | 5,1     | AA                 |
| `primary` sobre `background` (links)                              | 7,8     | AAA                |
| `primary-foreground` sobre `primary` (botões)                     | 7,9     | AAA                |
| `accent-foreground` sobre `accent`                                | 10,5    | AAA                |
| `highlight-foreground` sobre `highlight`                          | 8,3     | AAA                |
| `success-foreground` sobre `success-soft`                         | 8,4     | AAA                |
| `warning-foreground` sobre `warning-soft`                         | 8,0     | AAA                |
| `destructive` sobre `background`                                  | 5,1     | AA                 |
| branco sobre `success` (ícone ✓ da chamada/leitura)               | 4,5     | AA                 |
| branco sobre `emerald-600` (✓ da faixa da semana)                 | 3,7     | ok só como gráfico |
| `red-600` (InputError) sobre `background`                         | 4,5     | no limite          |
| **branco sobre `amber-500` (selo "Rascunho")**                    | **2,2** | **falha**          |
| **`input` (borda de campo) sobre `card`**                         | **1,5** | **falha 1.4.11**   |
| `border` sobre `card` (cartões)                                   | 1,3     | decorativo (ok)    |
| `ring` (foco) sobre `card`                                        | 3,8     | ok                 |

### Tema escuro (`app.css:183-213`)

| Par                                                       | Razão   | Resultado                    |
| --------------------------------------------------------- | ------- | ---------------------------- |
| `foreground` sobre `background`                           | 15,3    | AAA                          |
| `muted-foreground` sobre `background`                     | 7,0     | AAA                          |
| `primary` sobre `background`                              | 8,8     | AAA                          |
| `primary-foreground` sobre `primary`                      | 8,4     | AAA                          |
| **`destructive` sobre `background` (`text-destructive`)** | **4,3** | **falha AA (texto pequeno)** |
| `destructive-foreground` sobre `background`               | 6,5     | AA                           |
| branco sobre `destructive` (botão destrutivo)             | 4,3     | falha AA                     |
| **branco sobre `success` (✓ da chamada/leitura)**         | **2,5** | **falha 1.4.11**             |
| `input` sobre `card`                                      | 1,6     | falha 1.4.11                 |

**Onde isso aparece**

- Selo "Rascunho" branco sobre âmbar: `show.tsx:254`, `lesson-audio-manager.tsx:118`. Os selos de status da gestão usam `bg-amber-100 text-amber-900` (`resources/js/components/admin/status-badge.tsx:5`) — passa e é o padrão a copiar.
- `text-destructive` no escuro: erro de salvamento automático (`resources/js/components/autosave-status.tsx:31`), erro da IA (`study-helper.tsx:186`), erro do áudio (`resources/js/components/lesson/lesson-audio.tsx:193`).
- Borda dos campos (`--input`) em todos os `Input`/`Textarea`/`NativeSelect` (`resources/js/components/ui/input.tsx:10`, `resources/js/components/ui/native-select.tsx:14`). [HIP] Idosos com baixa visão podem não perceber onde digitar; o fundo `bg-card` sobre `bg-background` quase não difere (1,04:1).
- Transparências: `text-muted-foreground/70` (chevron do caminho, `page.tsx:30`), `text-warning-foreground/80` (`resources/js/pages/admin/dashboard.tsx:120`), `disabled:opacity-50/40/30` (setas de reordenar `opacity-30`, `resources/js/components/admin/reorder-buttons.tsx:49`) reduzem o contraste além do calculado.

---

## 5. Estados de carregamento, vazio e erro

**Bom**

- Vazios com ícone, título e orientação (`page.tsx:163-187`; ex.: `home.tsx:162-178`, `my-week.tsx:82-103`, `library/index.tsx:223-229`).
- Carregamento local com `Spinner` e texto ("Salvando…", "Buscando…", "Pensando na resposta…", "Entrando…") e `aria-live` (`library/index.tsx:183-197`, `study-helper.tsx:163-169`, `personal-note.tsx:51-53`).
- Barra de progresso do Inertia em navegações (`app.tsx:56-58`).

**Achados**

- [OBS] Chamada: erro sem ação de repetir (`resources/js/components/lesson/attendance-sheet.tsx:107`).
- [OBS] Erro da IA usa `role="alert"` (bom), mas a **resposta** que chega não é anunciada: só o "Pensando…" está em `aria-live` (`study-helper.tsx:163-169`); a bolha da resposta (`study-helper.tsx:144-158`) é inserida sem região viva.
- [OBS] Sem conexão: navegações do Inertia que falham não mostram nada próprio; navegação "dura" mostra `public/offline.html` (`public/sw.js:56-60`).
- [OBS] `useAutosave` não alerta ao sair da página com alteração pendente (o hook de guarda existe só para formulários da gestão: `resources/js/components/admin/lesson-form.tsx:59`).

---

## 6. Formulários

- **Teclado no celular** — bom: `type="tel"`/`inputMode="tel"` no WhatsApp (`complete-profile.tsx:62-66`), `type="email"`/`inputMode="email"` no login (`resources/js/pages/auth/login.tsx:44-50`), `enterKeyHint="search"` na busca (`library/index.tsx:115`), `autoComplete` em nome, e-mail, telefone, nascimento, senha (`complete-profile.tsx:54`, `67`, `92`, `114`, `138`), `passwordrules` (`complete-profile.tsx:139`), `autoCapitalize="off"`/`spellCheck={false}` no link colado (`resources/js/pages/auth/access-link.tsx:129-131`).
- **Achados**
    - [OBS] Erros sem ligação ao campo nas telas de aluno (ver §1). `InputError` tem `role="alert"` (`input-error.tsx:11`), então o erro é anunciado, mas ao voltar ao campo o leitor não diz que ele está inválido e a borda vermelha (`aria-invalid:border-destructive`, `input.tsx:12`) **não aparece** porque `aria-invalid` nunca é posto.
    - [OBS] O e-mail do cadastro não tem `inputMode="email"` (`complete-profile.tsx:110-119`) — `type="email"` já resolve no teclado; ok — mas também não tem `autoCapitalize="off"`; alguns teclados Android colocam a primeira letra maiúscula (o servidor já normaliza, `app/Http/Requests/CompleteProfileRequest.php:18-24`).
    - [OBS] Data de nascimento com `type="date"` (`complete-profile.tsx:88-97`). [HIP] Difícil para idosos no Android (seletor de calendário partindo do ano atual).
    - [OBS] Mostrar senha com `tabIndex={-1}` (`resources/js/components/password-input.tsx:27`): inacessível por teclado; o ícone tem 16 px dentro de uma área de ~40×44.
    - [OBS] "Manter conectado" é um checkbox de 16 px (`resources/js/components/ui/checkbox.tsx:14`) desmarcado por padrão (`login.tsx:79`). O rótulo está associado (toque no texto funciona), mas [HIP] idosos que entram por senha vão ser deslogados e voltar a depender do link.
    - [OBS] Enter envia a dúvida da IA (`study-helper.tsx:194-199`).

---

## 7. Acessibilidade (componentes e interação)

**Bom e deve ser preservado**

- Link "Pular para o conteúdo" nas duas cascas (`resources/js/layouts/app-layout.tsx:8-13`, `admin-layout.tsx:48-53`).
- `aria-current` nos menus e abas (`app-navigation.tsx:120`, `resources/js/components/admin/classroom-header.tsx:78`).
- Botões só com ícone com `aria-label` (Modo Domingo `sunday.tsx:113-131`, player `lesson-audio.tsx:115`, reordenar `reorder-buttons.tsx:50-59`, editar/remover na gestão).
- `aria-pressed` em "Marcar como lido" e na chamada (`reading-plan.tsx:120`, `attendance-sheet.tsx:126`).
- `BottomSheet`: título ligado (`bottom-sheet.tsx:144`), foco vai para o painel e volta ao gatilho (`bottom-sheet.tsx:89-104`), `inert` quando fechado (`bottom-sheet.tsx:141`), `motion-reduce:transition-none` (`bottom-sheet.tsx:146`); o Flowbite põe `role="dialog"` e `aria-modal` ao abrir (`node_modules/flowbite/lib/esm/components/drawer/index.js:138-139`).
- Referências bíblicas no estudo são `<button>` com foco visível (`app.css:292-294`, `resources/js/components/lesson/rich-text.tsx:32-44`).
- Zoom permitido (`viewport` sem `maximum-scale`, `resources/views/app.blade.php:5`); `lang` vem de `APP_LOCALE=pt_BR` (`app.blade.php:2`, `.env.example:7`).

**Problemas**

- **Alvos de toque < 44 px** (WCAG 2.5.8 pede 24 px; a meta do projeto para idosos deve ser 44 px):
    - Botões `size="sm"` = 32 px (`resources/js/components/ui/button.tsx:25`) usados em ações de aluno: "Ativar" lembretes (`resources/js/components/reminders-banner.tsx:59`), "Instalar"/"Ver como" (`resources/js/components/install-app-banner.tsx:90-98`), "Ver as N leituras" (`show.tsx:368-372`), "Compartilhar áudio" (`show.tsx:420-426`), WhatsApp na gestão (`resources/js/components/whatsapp-button.tsx:12`).
    - Botão padrão `h-10` = 40 px (`button.tsx:24`), `size="icon"` = 40 px (`button.tsx:27`).
    - Dispensar avisos: 32 px e **sem foco visível** (`reminders-banner.tsx:64`, `install-app-banner.tsx:102`).
    - Chips de classe no Início ≈ 32 px (`home.tsx:107`), faixa de seções 36 px (`section-nav.tsx:106`), "← Início" 36 px (`show.tsx:208`, `my-week.tsx:38`), "Todas as leituras" 36 px (`resources/js/components/week/week-summary.tsx:40`), "Ler tudo" 36 px (`resources/js/components/week/daily-blocks.tsx:46`), "Ler o texto" 40 px (`reading-plan.tsx:186`), avatar do menu 36 px (`resources/js/components/user-avatar.tsx:27`), itens do menu da conta ≈ 32 px (`resources/js/components/ui/dropdown-menu.tsx:75`), checkbox 16 px (`checkbox.tsx:14`), sugestões da IA ≈ 36 px (`study-helper.tsx:131`), "Tentar de novo" do salvamento sem área (`autosave-status.tsx:34-40`).
    - Gênero no cadastro 40 px (`resources/js/components/gender-field.tsx:30`).
- **Nomes acessíveis**
    - Vários "Ler o texto" iguais na mesma página, sem dizer qual leitura (`reading-plan.tsx:182-194`).
    - Faixa da semana: `aria-label` posto num `<span>` sem papel (`week-bar.tsx:37`) — leitores de tela costumam ignorar; o que se ouve é a letra "S" e depois "Seg". Hoje/futuro não são anunciados.
    - Vários "WhatsApp"/"Parabéns" iguais na lista (`resources/js/pages/admin/classrooms/show.tsx:666-673`, `719-726`).
    - Ícone de anotação com `aria-label` em SVG sem `role="img"` (`resources/js/pages/admin/classrooms/meetings/index.tsx:224-227`).
    - Paginação desabilitada é `<span aria-disabled>` (`library/index.tsx:296-301`, `314-319`).
- **Movimento**: `animate-pulse` no play destacado sem `motion-reduce` (`lesson-audio.tsx:121`); rolagens `behavior: 'smooth'` ignoram `prefers-reduced-motion` (`show.tsx:134`, `section-nav.tsx:82`, `study-helper.tsx:56`). Só a tela de abertura respeita (`resources/views/partials/splash.blade.php:179`).
- **Foco**: cartões da Biblioteca, atalhos do Início e linhas da gestão não têm `focus-visible` próprio (`library/index.tsx:234`, `home.tsx` `StudyTile` className, `dashboard.tsx:138`) — dependem do contorno padrão do navegador, que o `outline-none` não remove ali; ok, mas inconsistente com o resto.
- **Diálogos Radix**: corretos (título, descrição, fechar com rótulo, `resources/js/components/ui/dialog.tsx:51-72`). A sobreposição é `bg-black/80` (`dialog.tsx:39`), diferente do `bg-backdrop/60` do painel inferior.

---

## 8. Feedback (toasts)

- `sonner` no topo central (`app.tsx:51`), alimentado pelo flash do servidor (`resources/js/hooks/use-flash-toast.ts:6-46`); suporta ação ("Desfazer") com 10 s (`use-flash-toast.ts:31-43`); selos com 6 s (`use-flash-toast.ts:17-21`).
- [OBS] Mensagens importantes vão **só** para o toast com a duração padrão (~4 s): "Cadastro completo! Agora você entra com seu e-mail e senha." (`app/Http/Controllers/CompleteProfileController.php:50`), "Este aparelho vai lembrar de você." (`AccessLinkController.php:45`). [HIP] Idosos não leem em 4 s; a informação de que agora existe senha se perde.
- [OBS] O toast de selo usa emoji como conteúdo (`use-flash-toast.ts:18`); leitores de tela leem o nome do emoji.
- [OBS] Ações de grande efeito sem confirmação nem desfazer: publicar (`resources/js/pages/admin/lessons/edit.tsx:94`), "A lição continua no próximo" (`meetings/show.tsx:260-269`).

---

## 9. Páginas de erro

- Página própria para 403/404/500/503 com texto humano e dois caminhos (`resources/js/pages/error.tsx:7-63`, `bootstrap/app.php:66-75`). 419 vira toast + volta (`bootstrap/app.php:57-64`).
- [OBS] **429 não está na lista** (`bootstrap/app.php:66`): limites `throttle:access-link`, `throttle:library`, `throttle:downloads`, `throttle:engagement` (`routes/web.php:37`, `41`, `71-77`, `84-87`) caem na página padrão do framework, em inglês.
- [OBS] A página de erro mostra "Erro 404" em `text-xs` (`error.tsx:57-59`) — útil para suporte; ok.

---

## 10. PWA / instalação

- Manifesto completo, ícones maskable, atalhos, `lang` pt-BR (`public/manifest.webmanifest`); tutorial Android/iPhone (`resources/js/pages/install.tsx`); aviso de instalação no Início com prompt nativo quando disponível (`install-app-banner.tsx:39-109`).
- [OBS] `orientation: portrait-primary` (`public/manifest.webmanifest:9`) bloqueia paisagem no app instalado (Modo Domingo em tablet).
- [OBS] No iPhone, o app instalado não herda a sessão do Safari, e o tutorial manda colar o link de acesso (`install.tsx:176-184`). Pela regra de `app/Actions/Access/LoginWithAccessLink.php:36-37`, isso **apaga a senha** de quem já completou o cadastro e força criar outra (ver 07-B).
- [OBS] Sem leitura offline (`public/sw.js:5-7`).
- [OBS] `theme-color` fixo claro (`app.blade.php:34`); no tema escuro a barra de status fica verde-clara.

## 11. Notificações

- Convite discreto para lembretes, cede a vez ao aviso de instalar, some por 14 dias (`reminders-banner.tsx:7-36`); estados "bloqueado/sem suporte" explicados em Perfil (`resources/js/components/settings/reminders-row.tsx:45-48`); envio de teste (`reminders-row.tsx:102`).
- [OBS] O convite pede permissão ao tocar "Ativar" (correto: gesto do usuário). Não explica **quando** chegam os avisos (horário da leitura do dia, véspera). [HIP] Idosos negam permissões que não entendem.
- [OBS] Publicar dispara aviso para a classe sem confirmação (ver §8).

---

## 12. Correções de acessibilidade de **baixo risco** (sem mudança visual relevante)

Todas são mudanças de atributo, de classe de tamanho mínimo ou de texto invisível. Nenhuma altera layout de forma perceptível, exceto onde indicado "alvo cresce" (a área clicável aumenta, o desenho não).

| #   | Arquivo:linha                                                                                                                                                                                                                                                                                                                                                                                                                                                                               | Mudança                                                                                                                                                                                                                                                          |
| --- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 1   | `resources/js/pages/auth/complete-profile.tsx:51-57`, `62-73`, `88-97`, `110-119`, `135-142`, `157-163`                                                                                                                                                                                                                                                                                                                                                                                     | Adicionar `aria-invalid={errors.X ? true : undefined}` e incluir `X-error` em `aria-describedby`; dar `id="X-error"` ao `InputError` correspondente (ou trocar por `Field`). Idem em `pages/auth/login.tsx:43-53`, `66-74`, `pages/settings/profile.tsx:58-139`. |
| 2   | `resources/js/components/password-input.tsx:27`                                                                                                                                                                                                                                                                                                                                                                                                                                             | Remover `tabIndex={-1}` e acrescentar `aria-pressed={showPassword}`.                                                                                                                                                                                             |
| 3   | `resources/js/components/lesson/reading-plan.tsx:182-194`                                                                                                                                                                                                                                                                                                                                                                                                                                   | `aria-label={\`Ler o texto: ${reference}\`}` no botão "Ler o texto" (o rótulo começa pelo texto visível, respeitando 2.5.3).                                                                                                                                     |
| 4   | `resources/js/components/progress/week-bar.tsx:37`                                                                                                                                                                                                                                                                                                                                                                                                                                          | Trocar `aria-label` no `<span>` por um `<span className="sr-only">` com "segunda, lido / hoje / ainda não" e `aria-hidden` na letra; ou `role="img"` + `aria-label` completo (incluir "hoje" e "futuro").                                                        |
| 5   | `resources/js/components/reminders-banner.tsx:64` e `resources/js/components/install-app-banner.tsx:102`                                                                                                                                                                                                                                                                                                                                                                                    | Acrescentar `focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none` e área mínima `size-10` (alvo cresce 8 px).                                                                                                                                |
| 6   | `resources/js/components/lesson/lesson-audio.tsx:121`                                                                                                                                                                                                                                                                                                                                                                                                                                       | `animate-pulse` → `motion-safe:animate-pulse`. Mesmo em `resources/js/components/settings/avatar-picker.tsx:104`.                                                                                                                                                |
| 7   | `resources/js/pages/lessons/show.tsx:134`, `resources/js/components/section-nav.tsx:82`, `resources/js/components/lesson/study-helper.tsx:56`                                                                                                                                                                                                                                                                                                                                               | Usar `behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth'`.                                                                                                                                                                     |
| 8   | `resources/js/components/lesson/study-helper.tsx:146-158`                                                                                                                                                                                                                                                                                                                                                                                                                                   | Envolver a lista de respostas num contêiner `aria-live="polite"` (ou anunciar "Resposta pronta") para quem usa leitor de tela.                                                                                                                                   |
| 9   | `resources/js/pages/lessons/show.tsx:254` e `resources/js/components/admin/lesson-audio-manager.tsx:118`                                                                                                                                                                                                                                                                                                                                                                                    | `bg-amber-500 text-white` (2,2:1) → `bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-200`, as mesmas classes de `components/admin/status-badge.tsx:5`. Muda a cor do selo de forma pequena.                                                        |
| 10  | `resources/js/pages/admin/classrooms/show.tsx:666-673`, `719-726` + `resources/js/components/whatsapp-button.tsx`                                                                                                                                                                                                                                                                                                                                                                           | Prop opcional `ariaLabel` (ex.: "WhatsApp para Maria") repassada ao `<a>`.                                                                                                                                                                                       |
| 11  | `resources/js/pages/admin/classrooms/meetings/index.tsx:224-227`                                                                                                                                                                                                                                                                                                                                                                                                                            | Acrescentar `role="img"` ao ícone com `aria-label="Tem anotação"`.                                                                                                                                                                                               |
| 12  | `resources/js/pages/library/index.tsx:296-301`, `314-319`                                                                                                                                                                                                                                                                                                                                                                                                                                   | No `<span aria-disabled>` da paginação, usar `aria-disabled="true"` com `role="link"` ou simplesmente esconder o item desabilitado (`aria-hidden` + manter o espaço).                                                                                            |
| 13  | `resources/js/components/lesson/attendance-sheet.tsx:107`                                                                                                                                                                                                                                                                                                                                                                                                                                   | Ao lado de "Não salvou.", um `<button>` "Tentar de novo" que reenvia (reaproveitar o efeito com um contador como em `hooks/use-autosave.ts:46`). Texto novo, sem mudar o layout.                                                                                 |
| 14  | `resources/js/components/autosave-status.tsx:34-40`                                                                                                                                                                                                                                                                                                                                                                                                                                         | Botão "Tentar de novo" com `inline-flex min-h-11 items-center` e `focus-visible:ring-2` (alvo cresce, desenho igual).                                                                                                                                            |
| 15  | Alvos de 36–40 px para `min-h-11` (44 px): `pages/lessons/show.tsx:208`, `pages/my-week.tsx:38`, `pages/my-week.tsx:70`, `components/week/week-summary.tsx:40`, `components/week/daily-blocks.tsx:46`, `components/section-nav.tsx:106`, `components/lesson/reading-plan.tsx:186` (`min-h-10`→`min-h-11`), `components/lesson/passage-text.tsx:92`, `pages/library/index.tsx:202`, `components/gender-field.tsx:30` (`min-h-10`→`min-h-11`). Ganho de 4–8 px de altura; visual quase igual. |
| 16  | `resources/js/pages/home.tsx:107`                                                                                                                                                                                                                                                                                                                                                                                                                                                           | Chips de classe: `py-1.5` → `inline-flex min-h-11 items-center` + `focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none` (o mesmo de `my-week.tsx:70`).                                                                                       |
| 17  | `resources/js/components/ui/checkbox.tsx:14`                                                                                                                                                                                                                                                                                                                                                                                                                                                | Manter o desenho de 16 px, mas ampliar a área de toque com pseudo-elemento: `relative after:absolute after:-inset-3.5 after:content-['']`.                                                                                                                       |
| 18  | `resources/js/components/lesson/study-helper.tsx:117`                                                                                                                                                                                                                                                                                                                                                                                                                                       | Completar a descrição: "A IA responde com base nesta lição. Seu professor vê as perguntas da turma, sem nomes." (transparência; texto, não visual).                                                                                                              |
| 19  | `bootstrap/app.php:66`                                                                                                                                                                                                                                                                                                                                                                                                                                                                      | Incluir 429 na lista e uma mensagem em `resources/js/pages/error.tsx:7-28` ("Muitas tentativas seguidas. Espere um minuto e tente de novo.").                                                                                                                    |

Itens que **não** entram nesta lista por mudarem o visual: borda dos campos (`--input`, 1,5:1), `text-destructive` no tema escuro (4,3:1) e branco sobre `success` no escuro (2,5:1). Recomendação: escurecer `--input` para ~`oklch(0.7 0.014 80)` (≈3:1) e usar `--destructive-foreground` para texto de erro no escuro — validar com o designer.

---

## 13. Hipóteses para o teste no navegador (próximo agente)

1. Com fonte do sistema em 200% (Android) e "Texto maior" (iOS), o menu inferior e a faixa da semana continuam legíveis?
2. Com o mini player e "Tirar dúvida" visíveis num celular de 360×640, quanto da tela sobra para o estudo?
3. Leitor de tela (TalkBack): a faixa da semana, "Ler o texto" e a resposta da IA são compreensíveis?
4. Fluxo B completo num Android com seletor de data nativo: quantos toques para chegar a 1950?
5. Reabrir o link pessoal depois de criar senha: confirmar que volta ao "Completar cadastro".
