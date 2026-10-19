# 07 — Jornadas de usuário (EBD 360)

Auditoria feita **só pela leitura do código** (React 19 + Inertia 3 + Tailwind 4, Laravel). Nada foi executado. Cada afirmação tem `caminho:linha`.

Legenda usada em todo o documento:

- **[OBS]** problema observado no código (o comportamento decorre do que está escrito).
- **[HIP]** hipótese a validar com usuários reais (idosos, quem chega pelo WhatsApp, professores).
- **Esforço** = toques/telas contados no código, partindo do ponto de entrada indicado, sem contar digitação.

Público considerado: membros da igreja, muitos idosos ou pouco habituados a apps, que chegam pelo link do WhatsApp no celular; professores (às vezes também idosos) que conduzem a aula com celular/tablet; um administrador.

---

## Visão geral das superfícies

| Área                  | Casca                                        | Navegação                                        | Evidência                                                                                   |
| --------------------- | -------------------------------------------- | ------------------------------------------------ | ------------------------------------------------------------------------------------------- |
| App de estudo         | `AppLayout` (topo + menu inferior)           | Início · Biblioteca · Perfil/Entrar              | `resources/js/layouts/app-layout.tsx:5`, `resources/js/components/app-navigation.tsx:48-71` |
| Modo Domingo          | sem casca (tela cheia)                       | "Sair" + A−/A+                                   | `resources/js/app.tsx:29-31`, `resources/js/pages/lessons/sunday.tsx:96-134`                |
| Autenticação/cadastro | `AuthLayout`                                 | —                                                | `resources/js/app.tsx:33-34`                                                                |
| Gestão                | `AdminLayout` (topo próprio + menu inferior) | Painel · Classes · Lições · Séries               | `resources/js/layouts/admin-layout.tsx:34-44`                                               |
| Abas de uma classe    | `ClassroomHeader`                            | Resumo · Domingos · Alunos · Dúvidas · Relatório | `resources/js/components/admin/classroom-header.tsx:30-53`                                  |

Pontos fortes que valem para várias jornadas (para não serem perdidos numa refatoração):

- Link de pessoal com token no **fragmento** (`#token`), lido no cliente e enviado por POST: o preview do WhatsApp não consome o link (`app/Http/Controllers/AccessLinkController.php:12-18`, `resources/js/pages/auth/access-link.tsx:70-93`).
- Leitor de leitura em painel inferior com "Marcar como lido" fixo ao alcance do polegar (`resources/js/components/lesson/reading-sheet.tsx:69-73`).
- Salvamento automático com estado visível ("Salvando… / Salvo / Tentar de novo") nas anotações (`resources/js/components/autosave-status.tsx:5-44`).
- Linguagem simples e acolhedora nos estados vazios e erros (`resources/js/pages/error.tsx:7-28`, `resources/js/pages/home.tsx:162-178`).

---

## A — Visitante recebe a lição pelo WhatsApp (rota pública)

**Objetivo:** abrir a lição pelo link e ler/ouvir, sem conta.

**Passos e telas**

1. Toca no link `…/licoes/{slug}` (rota pública `routes/web.php:33`). Tela de abertura (splash) e depois `resources/js/pages/lessons/show.tsx`.
2. Vê cabeçalho (classe, série, número, título, data, contagem regressiva) `show.tsx:206-268`, texto base com versículos `show.tsx:270-277`, versículo-chave/alvo `show.tsx:279-283`, botões Compartilhar/Modo Domingo `show.tsx:285-303`, resumo `show.tsx:305-309`, faixa de seções fixa `show.tsx:308-315`.
3. "Preparação da semana": mostra **só uma** leitura quando há mais de duas (`show.tsx:84-85`, `show.tsx:331-337`), botão "Ver as N leituras" (`show.tsx:362-382`).
4. "Ler o texto" abre o painel com o texto bíblico (`resources/js/components/lesson/reading-plan.tsx:182-194`).
5. Estudo (`show.tsx:409-436`), áudio se existir (`show.tsx:407-424`), aprofunde/curiosidades/conceitos/materiais/referências.

**Dados usados:** `LessonResource` com conteúdo de aluno apenas (`app/Http/Controllers/LessonController.php:175-193`), texto de compartilhamento (`LessonController.php:33`).

**Permissões:** `Gate::allows('view')`. Lição "só membros" + visitante → redireciona para **login por e-mail e senha** (`LessonController.php:66-70`); logado sem acesso → 404 (`LessonController.php:72-74`).

**Esforço observável:** 1 toque para abrir; +1 para ler a leitura do dia; +1 para expandir todas as leituras; +1 para ouvir o áudio (o link `#ouvir` só destaca o player, não toca: `show.tsx:113-142`).

**Fricções**

- [OBS] Lição restrita leva o visitante à tela de e-mail/senha (`LessonController.php:68`), que ele não tem; a orientação é "peça um novo link" (`resources/js/pages/auth/login.tsx:97-101`). Para um visitante de verdade, é beco sem saída.
- [OBS] O único convite para o visitante é "Membro da classe? Entre com o seu link…" (`show.tsx:389-404`). Não há caminho para "quero participar / onde e quando é a EBD".
- [OBS] O link "Entrar" desse convite aponta para `login()` (`show.tsx:396`), não para "Entrar com meu link" (`/entrar`) — o membro que perdeu o link cai no formulário de senha.
- [OBS] A página é longa e densa para quem chega sem contexto: até 9 seções na faixa (`show.tsx:157-176`), botões, selos e contagem antes do primeiro parágrafo do estudo.
- [HIP] Visitantes idosos podem não perceber que "Ler o texto" abre um painel e que a lista de leituras foi recolhida.

**Casos de erro:** 404 com texto amigável e atalhos (`resources/js/pages/error.tsx:13-17`). [OBS] 429 (limites `throttle:library`, `throttle:downloads`) **não** tem página própria: só 403/404/500/503 são renderizados (`bootstrap/app.php:66`), o resto cai na página padrão do framework.

**Oportunidades**

- Para visitante não logado, um bloco curto no fim: "Quer participar? A EBD é aos domingos às __h" + botão de WhatsApp da secretaria (dado novo de configuração).
- Trocar o link do convite para `/entrar` com texto "Entrar com meu link".
- Em lição restrita, uma tela intermediária ("Esta lição é para alunos da classe. Peça o seu link ao professor") em vez do formulário de senha.

**Hipóteses a validar:** quantos visitantes chegam pelo link (log de acesso sem identificação); se entendem o painel de leitura; se procuram horário/local.

---

## B — Aluno acessa o link pessoal pela primeira vez (completar cadastro)

**Objetivo:** entrar uma vez e continuar logado no aparelho.

**Passos e telas**

1. Toca `…/entrar#TOKEN` (`routes/web.php:84-87`). `resources/js/pages/auth/access-link.tsx` lê o fragmento, apaga da barra e faz POST automático (`access-link.tsx:70-93`).
2. Se o cadastro está incompleto: toast "Falta só completar o seu cadastro" e redireciona (`app/Http/Controllers/AccessLinkController.php:39-43`).
3. `resources/js/pages/auth/complete-profile.tsx`: **7 campos obrigatórios** — nome (preenchido), WhatsApp, data de nascimento, gênero, e-mail, senha, repetir senha (`complete-profile.tsx:46-186`; regras em `app/Http/Requests/CompleteProfileRequest.php:31-37`).
4. "Salvar e continuar" → Início (`app/Http/Controllers/CompleteProfileController.php:46-53`).

**Permissões:** link nunca autentica professor/admin (`app/Actions/Access/LoginWithAccessLink.php:29-31`). O middleware bloqueia **todas** as rotas, inclusive a lição pública, enquanto o cadastro não termina (`app/Http/Middleware/EnsureProfileIsComplete.php:22`, registrado em `bootstrap/app.php:37`).

**Esforço observável:** 1 toque no link + ~7 campos + 2 toques (gênero, salvar) ≈ 10 interações e 3 telas antes de ver qualquer lição. O seletor nativo `type="date"` (`complete-profile.tsx:88-97`) exige navegar por décadas no Android para quem nasceu em 1950.

**Fricções**

- [OBS] **Reentrar pelo mesmo link apaga a senha criada**: `LoginWithAccessLink.php:36-37` zera a senha de quem já tem uma; `User::hasCompleteProfile()` exige senha (`app/Models/User.php:216-222`), então a pessoa volta para "Completar cadastro" e precisa criar outra senha. Quem guardou o link do WhatsApp como "o jeito de entrar" vai cair nisso toda vez que a sessão expirar ou trocar de aparelho.
- [OBS] O tutorial de instalação no iPhone manda colar o link de acesso dentro do app instalado (`resources/js/pages/install.tsx:176-184`) — o que, pelo item acima, apaga a senha e força novo cadastro.
- [OBS] E-mail obrigatório (`CompleteProfileRequest.php:32`) mesmo com recuperação por e-mail desligada (`config/ebd.php:48`). [HIP] Parte dos idosos não tem ou não lembra o e-mail; o campo vira barreira sem benefício imediato para eles.
- [OBS] Erros de validação não são ligados aos campos: os `<Input>` não recebem `aria-invalid` nem `aria-describedby` do erro (`complete-profile.tsx:51-57`, `69-73`, `88-97`, `110-119`), embora exista o componente `Field` que faz isso (`resources/js/components/form-field.tsx:33-40`).
- [OBS] O botão de mostrar senha está fora da ordem de tabulação (`resources/js/components/password-input.tsx:27`).
- [OBS] Com outra pessoa logada no aparelho (celular compartilhado na família), a tela pede "Trocar e entrar" (`access-link.tsx:162-178`) — bom; mas trocar desloga a outra pessoa sem aviso do que acontece com ela.

**Casos de erro:** link inválido/revogado → mensagem clara (`LoginWithAccessLink.php:20`). Colar algo que não é link → mensagem orientando a copiar de novo (`access-link.tsx:107-112`).

**Oportunidades**

- Não apagar a senha quando o link é usado por quem já completou o cadastro e está num aparelho novo; tratar "recuperação" como ação explícita do professor (gerar link de recuperação).
- Dividir o cadastro em dois momentos: o mínimo para entrar (nome + WhatsApp) e o resto depois, sem bloquear a lição pública.
- Data de nascimento em três campos (dia/mês/ano) com `inputmode="numeric"`, ou seletor de ano primeiro.

**Hipóteses a validar:** taxa de abandono no "Completar cadastro" (o próprio app já marca "cadastro pendente" na lista de alunos: `resources/js/pages/admin/classrooms/students/index.tsx:180-182`); quantos alunos voltam a usar o link depois de criar senha.

---

## C — Aluno estuda durante a semana

**Objetivo:** fazer a leitura do dia, acompanhar o progresso, anotar, tirar dúvidas, ouvir o estudo.

**Passos e telas (a partir do Início, `resources/js/pages/home.tsx`)**

1. Saudação + contagem para domingo (`home.tsx:80-89`, `home.tsx:262-276`).
2. Cartão da lição (`LessonHero`), depois "Sua semana" com os 7 dias, leituras feitas e sequência (`resources/js/components/week/week-summary.tsx:31-81`).
3. "Ler o texto" da leitura de hoje → painel → "Marcar como lido" (`home.tsx:418-434`, `reading-plan.tsx:182-194`, `reading-plan.tsx:109-145`).
4. Bloco "Para hoje" (curiosidade/conceito liberado no dia) (`resources/js/components/week/daily-blocks.tsx:27-53`).
5. Atalhos: Lição, Leituras, Materiais, Modo Domingo, Anotações, Meu progresso (`home.tsx:315-374`).
6. Anotações: atalho leva a `/licoes/{slug}#anotacoes`; texto salvo sozinho após 1,2 s (`resources/js/hooks/use-autosave.ts:15`, `resources/js/components/lesson/personal-note.tsx:20-34`).
7. Dúvida com IA: botão flutuante "Tirar dúvida" + chamada ao fim do estudo (`resources/js/components/lesson/study-helper.tsx:274-292`, `250-268`); limite diário (`study-helper.tsx:225-229`).
8. Áudio: player no topo do Estudo e mini player persistente (`resources/js/components/lesson/lesson-audio.tsx:104-198`, `resources/js/components/lesson/mini-audio-player.tsx:63-120`).

**Dados:** `StudyWeek` (dias, progresso, sequência, blocos do dia) do `MyWeekController`/`HomeController`; check-ins em `reading_checkins`; anotação em `lesson_notes` (`LessonController.php:127-147`).

**Permissões:** marcar leitura, anotar e IA exigem `auth` e ser membro (`routes/web.php:41-55`, `LessonController.php:131-133`, `LessonController.php:155-160`).

**Esforço observável:** leitura do dia = 2 toques a partir do Início (Ler o texto → Marcar como lido). Anotar = 1 toque (atalho) + rolagem automática + digitação. Dúvida = 1 toque + digitação + enviar. Ouvir = abrir a lição (1) + tocar play (1).

**Fricções**

- [OBS] O atalho "Lição" abre o **PDF em nova aba** quando há material principal, e não o estudo do app (`home.tsx:318-330`); o mesmo rótulo leva a lugares diferentes conforme a lição. [HIP] O idoso sai do app e não sabe voltar.
- [OBS] "Leituras da semana" não está no menu; só se chega pelo atalho/link "Todas as leituras" (`week-summary.tsx:38-43`, `resources/js/components/app-navigation.tsx:44-47` explica a escolha).
- [OBS] As respostas da IA se perdem ao fechar o painel ("as respostas desta visita ficam listadas… mas não viram conversa", `study-helper.tsx:32-36`); não há "guardar na minha anotação".
- [OBS] O aluno não é avisado de que as perguntas aparecem (anônimas) para o professor na aba Dúvidas (`resources/js/pages/admin/classrooms/questions.tsx:53-58`); o painel diz só "A IA responde com base nesta lição" (`study-helper.tsx:117`), e "Meu progresso" diz que o professor "nunca" vê as anotações (`resources/js/pages/my-progress.tsx:47`) sem falar das dúvidas.
- [OBS] Enter envia a dúvida (`study-helper.tsx:194-199`). [HIP] Teclados do celular mostram "Enter" como quebra de linha; idosos podem enviar pela metade.
- [OBS] Anotação: se a pessoa fecha a aba antes de 1,2 s ou sem rede, o texto se perde; não há rascunho local (`use-autosave.ts:27-42`).
- [OBS] A sequência ("Leia hoje para manter a sequência", `resources/js/components/progress/streak-flame.tsx:32`) cobra diariamente. [HIP] Pode gerar culpa em quem lê na Bíblia impressa ou não lê todo dia; ver 09.

**Casos de erro:** erro ao marcar leitura → estado "Salvando…" e botão desabilitado (`reading-plan.tsx:120-144`); IA: 429, sem conexão e erro genérico com texto claro (`study-helper.tsx:84-104`); áudio falhou (`lesson-audio.tsx:191-196`).

**Oportunidades:** botão "Guardar esta resposta nas minhas anotações"; aviso de privacidade de uma linha no painel de dúvidas; atalho "Lição" sempre para o estudo no app e o PDF como item de Materiais; rascunho local da anotação.

**Hipóteses a validar:** quantos marcam leitura sem abrir o texto; se a sequência motiva ou constrange (entrevista, não métrica); uso do áudio por quem tem baixa visão.

---

## D — Aluno no domingo (Modo Domingo)

**Objetivo:** acompanhar a aula no celular com letra grande.

**Passos e telas:** atalho "Modo Domingo" no Início (`home.tsx:352-357`) ou na lição (`show.tsx:291-295`) → `resources/js/pages/lessons/sunday.tsx`. Tela sem navegação, A−/A+ lembrado no aparelho (`sunday.tsx:34-47`, `sunday.tsx:66-76`), título, texto base grande, versículo-chave/alvo, tópicos, "Estudo completo" recolhido em `<details>` (`sunday.tsx:263-271`).

**Permissões:** mesmas da lição (`LessonController.php:42-58`). Blocos de professor só para quem gerencia (`sunday.tsx:189-201`, `221-247`).

**Esforço observável:** 2 toques a partir do Início (Modo Domingo) ou 1 a partir da lição.

**Fricções**

- [OBS] O aluno não vê no Modo Domingo as **próprias anotações nem as dúvidas** que levou da semana — o placeholder da anotação diz "Dúvidas para levar no domingo…" (`resources/js/components/lesson/personal-note.tsx:43`), mas o Modo Domingo não as mostra.
- [OBS] O manifesto trava o app instalado em retrato (`public/manifest.webmanifest:9`). [HIP] Professores que conduzem pelo tablet deitado ficam sem paisagem no app instalado.
- [OBS] O app não funciona sem rede: páginas nunca vão para o cache, só `offline.html` (`public/sw.js:5-7`, `public/sw.js:56-60`). [HIP] Salas de EBD com sinal ruim.

**Oportunidades:** seção "Minhas anotações" (só leitura) no Modo Domingo para membros; permitir orientação livre; cache da lição da semana para leitura offline (só conteúdo público).

---

## E — Aluno consulta estudos anteriores

**Objetivo:** reencontrar uma aula, reler, rever o que anotou.

**Passos e telas:** "Aulas anteriores" no Início (`home.tsx:180-219`) ou "Biblioteca" no menu (`resources/js/pages/library/index.tsx`): busca enquanto digita (350 ms, `library/index.tsx:64-75`), filtros Classe/Série/Ano (`library/index.tsx:118-176`), lista paginada (`library/index.tsx:291-339`). "Meu progresso" → "Lição a lição" com links (`my-progress.tsx:66-71`, `92-160`).

**Esforço observável:** 1 toque (Biblioteca) + busca/filtro + 1 toque na aula.

**Fricções**

- [OBS] Não há lista "minhas anotações" nem indicação, na Biblioteca, de quais aulas a pessoa leu ou anotou; para rever o que escreveu é preciso abrir aula por aula.
- [OBS] O filtro "Série" troca de posição entre celular e computador (`order-first col-span-2`, `library/index.tsx:136`) — inofensivo, mas a ordem de tabulação fica diferente da visual no celular.
- [OBS] Na paginação, o botão desabilitado é um `<span aria-disabled>` dentro de `Button asChild` (`library/index.tsx:310-318`) — leitores de tela não anunciam como botão.

**Oportunidades:** página "Minhas anotações" (lista por lição, só da pessoa); marcador discreto "você anotou" no cartão da Biblioteca.

---

## F — Professor prepara a lição

**Objetivo:** criar a lição, preencher estudo, blocos, leituras, materiais, áudio e publicar.

**Passos e telas:** Painel → "Nova lição" (`resources/js/pages/admin/dashboard.tsx:85-90`) → `admin/lessons/create.tsx` (formulário `LessonForm`, 11 campos `resources/js/components/admin/lesson-form.tsx:95-284`) → salva como rascunho → `admin/lessons/edit.tsx` com seções Dados, Áudio, Domingos, Aprofundamento, Leituras, Materiais (`edit.tsx:201-224`) → "Publicar" (`edit.tsx:94`, `edit.tsx:168-180`).

**Permissões:** `can:access-admin` (`routes/web.php:104`, `app/Providers/AppServiceProvider.php:109`) + `update` da lição.

**Esforço observável:** criar = 1 + 11 campos + salvar; cada bloco/leitura/material = abrir formulário + campos + salvar; reordenar = 1 toque por posição (`resources/js/components/admin/reorder-buttons.tsx:43-63`). Publicar = 1 toque.

**Fricções**

- [OBS] **Publicar não pede confirmação** (`edit.tsx:94` não tem `confirm`, só "Despublicar" tem, `edit.tsx:95-100`), mas publicar dispara aviso no celular da classe inteira (`app/Http/Controllers/Admin/LessonStatusController.php:29-35`). Toque acidental = notificação para todos.
- [OBS] Não há checagem pré-publicação (lição sem domingo, sem leituras, áudio desatualizado). O aviso "Rascunho: só professores…" (`edit.tsx:192-197`) é o único lembrete.
- [OBS] Leitura com referência não reconhecida é sinalizada ("Sem texto: referência não reconhecida", `resources/js/components/admin/readings-manager.tsx:78-83`) — bom; mas não impede publicar.
- [OBS] A página de edição é muito longa no celular (6 seções empilhadas, `edit.tsx:226-360`), e o botão "Publicar" fica só no topo.

**Oportunidades:** diálogo de confirmação ao publicar com um checklist ("1 domingo marcado · 6 leituras · áudio gerado") e o número de aparelhos que serão avisados; botão de publicar repetido no fim da página.

---

## G — Professor organiza o calendário

**Objetivo:** ter cada domingo com a lição certa, marcar "Sem EBD", restaurar, estender uma lição.

**Passos e telas:** Classe → aba Domingos (`resources/js/pages/admin/classrooms/meetings/index.tsx:63-120`) → "Planejar série" (`resources/js/components/admin/meetings/plan-meetings-dialog.tsx:73-131`) → domingo (`meetings/show.tsx`) com seletor de lição + "Salvar lição" explícito (`meetings/show.tsx:353-410`) e "Outras ações": continua no próximo, teve aula sem chamada, sem EBD, editar data (`meetings/show.tsx:258-305`).

**Esforço observável:** trocar a lição de um domingo = 3 toques (abrir domingo, escolher, salvar). Cancelar = 2 + motivo opcional + confirmar.

**Fricções**

- [OBS] "A lição continua no próximo" executa direto, **sem confirmação e sem desfazer** (`meetings/show.tsx:259-269`, `app/Http/Controllers/Admin/MeetingStatusController.php:39-45`), e empurra as lições seguintes.
- [OBS] O status do domingo (planejado/realizado/cancelado) some no celular (`hidden sm:inline-flex`, `meetings/index.tsx:231-235`); fica só o risco no número e a cor do texto para "cancelado".
- [OBS] O link "Ver domingos mais antigos" é texto pequeno sem área mínima (`meetings/index.tsx:106-114`).

**Oportunidades:** confirmação curta ou toast com "Desfazer" (o `useFlashToast` já suporta ação, `resources/js/hooks/use-flash-toast.ts:31-43`).

---

## H — Chamada e encerramento

**Objetivo:** marcar presentes durante a aula e encerrar, registrando onde parou.

**Passos e telas:** Modo Domingo (professor) → barra fixa "Chamada N/M" + "Encerrar aula" (`sunday.tsx:276-303`) → painel com nomes (toque = presente, salva sozinho em 800 ms) e contador de visitantes (`resources/js/components/lesson/attendance-sheet.tsx:49-70`, `116-176`) → "Encerrar aula": nota "Onde paramos" + "Lição concluída"/"Continua no próximo" (`resources/js/components/lesson/finish-meeting-dialog.tsx:56-94`). Alternativa: página do domingo na gestão (`meetings/show.tsx:184-230`).

**Esforço observável:** 1 toque para abrir + 1 por aluno presente + 2 para encerrar.

**Fricções**

- [OBS] Se salvar falhar, aparece "Não salvou. Tente de novo." (`attendance-sheet.tsx:107`) mas **não há botão para tentar** — só reenviando ao tocar outro nome. Wi-Fi da igreja instável é o cenário citado no próprio comentário (`attendance-sheet.tsx:14-16`).
- [OBS] Sem busca/ordenação por sobrenome ou "presentes primeiro"; em classe grande é rolar a lista inteira (`attendance-sheet.tsx:116-160`).
- [OBS] A chamada só abre no dia (`LessonController.php:96`, `sunday.tsx:141-155`) — correto, mas o professor que testa no sábado só vê o aviso.

**Oportunidades:** botão "Tentar de novo" ao lado do erro; filtro por nome quando a lista passa de ~15 pessoas.

---

## I — Professor acompanha alunos em risco

**Objetivo:** perceber quem precisa de atenção e entrar em contato.

**Passos e telas:** Classe → Resumo → "Precisam de atenção" (`resources/js/pages/admin/classrooms/show.tsx:624-680`), regra: N faltas seguidas **ou** N dias sem marcar leitura (`app/Queries/StudentsNeedingAttention.php:37-50`, `config/ebd.php:53-55`); botão WhatsApp com mensagem pronta (`show.tsx:666-673`, `resources/js/lib/whatsapp.ts:15-17`). Lista de Alunos com chips "precisa de atenção", "nunca entrou", "cadastro pendente" (`students/index.tsx:166-182`).

**Esforço observável:** 2 toques (Classe → WhatsApp) para mandar a mensagem.

**Fricções**

- [OBS] A mensagem pronta é sempre "Sentimos sua falta na EBD…" (`resources/js/lib/whatsapp.ts:16`) mesmo quando o motivo é só "dias sem leitura" no app (`StudentsNeedingAttention.php:49`). Um aluno presente todo domingo, que lê na Bíblia impressa, recebe "sentimos sua falta" — constrangedor.
- [OBS] "Sem leitura" mede marcação no app, não estudo. Ver 09.
- [OBS] O botão WhatsApp é `size="sm"` (32 px, `resources/js/components/whatsapp-button.tsx:12`, `resources/js/components/ui/button.tsx:25`) e tem o mesmo nome acessível em todas as linhas ("WhatsApp").

**Oportunidades:** mensagem conforme o motivo (falta → "sentimos sua falta"; só leitura → nenhuma mensagem pronta, ou algo como "Separei a leitura desta semana para você"); separar "faltas" de "sem marcação no app" na lista.

---

## J — Administrador organiza classes, séries e professores

**Objetivo:** criar classes, séries, atribuir professores.

**Passos e telas:** Gestão → Classes (`resources/js/pages/admin/classrooms/index.tsx`) → Nova/Editar (`classrooms/form.tsx:62-115`) → Professores por e-mail (`classrooms/form.tsx:163-228`); Séries (`admin/series/index.tsx`, `series/form.tsx:73-181`).

**Esforço observável:** classe = 3 campos + salvar; professor = digitar e-mail + adicionar; série = 5 campos + salvar.

**Fricções**

- [OBS] Professor precisa já ter conta com e-mail (`classrooms/form.tsx:166`); não há convite.
- [OBS] O menu inferior da gestão tem 4 itens + não tem "Voltar ao app" (fica no topo, `admin-layout.tsx:74-87`). Bom para foco; [HIP] professores idosos podem não achar o caminho de volta ao estudo.

**Oportunidades:** convite por link para professor; resumo "o que falta configurar" no Painel para o admin.

---

## Resumo do esforço (código)

| Jornada                     | Toques até o objetivo principal | Telas                |
| --------------------------- | ------------------------------- | -------------------- |
| A — ler lição pública       | 1 (abrir) + 1 (leitura)         | 1 + painel           |
| B — primeiro acesso         | 1 + ~7 campos + 2               | 3                    |
| C — leitura do dia          | 2 a partir do Início            | 1 + painel           |
| D — Modo Domingo            | 2 a partir do Início            | 1                    |
| E — achar aula antiga       | 1 + busca + 1                   | 2                    |
| F — publicar                | criar (12) + seções + 1         | 2                    |
| G — trocar lição do domingo | 3                               | 2                    |
| H — chamada                 | 1 + 1/aluno + 2                 | 1 + painel + diálogo |
| I — contatar aluno          | 2                               | 1                    |
| J — nova classe             | 4                               | 2                    |
