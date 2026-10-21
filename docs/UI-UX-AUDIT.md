# Auditoria de UI/UX — Didaché (EBD)

Data: 2026-10-19 · Branch: `feat/ui-ux-auditoria` (a partir da `main` em `682d076`)
Referência de boas práticas: skill `ui-ux-pro-max` (prioridades: acessibilidade → toque → layout responsivo → tipografia/cor → formulários → navegação), cruzada com a auditoria anterior de código em `docs/engineering/ebd-360/08-ux-accessibility.md`.

## Como foi feito e o que vale cada evidência

| Marca       | Significa                                                                                                                                                                                                      |
| ----------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **[NAV]**   | Visto navegando em produção (`https://ebd.up.railway.app`) no Chrome do Simon, com a sessão dele (dono + admin + professor da classe Jovens), desktop 1568 px. Só leitura: nada foi salvo, enviado ou apagado. |
| **[LOCAL]** | Visto no app local (mesma `main`, banco `ebd_ui` semeado, contas do seed) com Playwright em 360 px com fonte do sistema a 125 %, 390 px e 768 px.                                                              |
| **[CÓD]**   | Confirmado lendo o código.                                                                                                                                                                                     |
| **[HIP]**   | Hipótese de UX: plausível, mas precisa de teste com pessoas reais.                                                                                                                                             |

Limitações: o Chrome do Simon estava maximizado e não aceitou redimensionar, então mobile e tablet foram vistos só no ambiente local (dados fictícios, mesmas telas). Não testei telas deslogadas (login, link de acesso, cadastro), Modo Domingo em uso real nem a tela de edição de lição em profundidade. Nenhum dado pessoal de alunos foi copiado para este documento.

---

## 1. Diagnóstico geral

O produto está **bem acima da média** de sistemas de igreja: linguagem humana ("Faltam 6 dias para a nossa próxima EBD", "Toque no nome de quem estava. Salva sozinha"), tipografia serifada de leitura confortável, navegação curta (3 itens no app, 4–5 na gestão), estados vazios com orientação, chamada em grade de toque grande e nenhum estouro horizontal nas 36 combinações tela × rota testadas. A auditoria anterior (EBD-360) já cobriu contraste e alvos de toque em detalhe.

Os problemas que sobram **não são de CSS**: são de **coerência da informação** — a mesma coisa contada de dois jeitos, números que falam de uma série enquanto a tela fala de outra, listas em que o que importa disputa espaço com o que não importa. São os erros que só aparecem quando a pessoa usa de verdade:

1. **Um erro de produção real**: o avatar não carrega em nenhuma página, porque os arquivos são servidos por um domínio (`usedidache.com.br`) que ainda não resolve no DNS.
2. **Números que se contradizem**: o Início e "Leituras da semana" dizem "1 de 6 leituras", a lição diz "1 de 7 leituras feitas" — com 7 leituras na lista.
3. **Contexto desencontrado na gestão**: a classe estuda Efésios (próximo domingo), mas o cabeçalho, a lista de alunos e os números dizem "Milagres de Jesus" (regra de "série anterior até a nova começar", correta no cálculo, invisível na interface).
4. **Ruído competindo com o essencial**: o Painel mistura as 3 próximas aulas com 5 rascunhos sem data de outra série, em ordem aleatória, com o selo "SEM —"; a agenda repete "Planejado" em todos os 12 domingos futuros, inclusive nos que não têm lição.
5. **Escolhas longas sem estrutura**: o seletor "Lição do dia" lista as ~30 lições de três séries misturadas por número ("Lição 1 — …", "Lição 1 — …", "Lição 2 — …").
6. **Celular**: o nome da igreja fica por baixo do botão "App" no topo da gestão; na lista de Lições a 360 px com fonte grande o título é espremido em ~50 px ("É Necessá…").

---

## 2. Perfis e jornadas percorridas

| Perfil             | Jornada                                   | Resultado                                                                                                                                                  |
| ------------------ | ----------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Aluno recorrente   | Início → leitura de hoje → lição → seções | Bom. 1 toque até a leitura do dia. Atrito: contagem 6 × 7; a lição empilha cabeçalho, texto base, versículo, alvo, 3 botões e resumo antes do estudo.      |
| Aluno              | Meu progresso                             | Selos usam emoji (🔥📖🌟🏅⛪) — destoam do restante (ícones de traço) e do guia visual do projeto ("ícones geométricos, nada de ilustração").              |
| Professor          | Painel → classe → domingo → chamada       | Bom: 3 toques até a chamada, salva sozinha. Atrito: seletor de lição sem grupos; "A lição continua no próximo" é ambíguo.                                  |
| Professor          | Classe → "Precisam de atenção"            | 9 de 14 alunos marcados, inclusive quem tem 100 % de presença e só não marcou leitura. Alerta em 64 % da turma deixa de ser alerta. [HIP sobre o critério] |
| Líder/admin        | Painel → Lições → filtrar                 | A primeira série da lista é um rascunho antigo de 24 lições; a série em curso aparece depois.                                                              |
| Iniciante (gestão) | Onde mudo os dados da igreja?             | Só pelo nome da igreja no topo (com uma setinha de 12 px). "Programas" está escondido dentro de "Classes".                                                 |
| Dono               | Dono → Igrejas / Biblioteca oficial       | Claro e objetivo. Sem achados relevantes.                                                                                                                  |

---

## 3. Inventário de problemas

Prioridade: **C**rítica · **A**lta · **M**édia · **B**aixa. Esforço: baixo · médio · alto.

### 3.1 Transversais

| #   | Problema                                      | Evidência                                                                                                                                                                                                                                                                                    | Impacto                                                                              | Proposta                                                                                                                                                                                                                                                                                          | Prio  | Esforço |
| --- | --------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----- | ------- |
| T1  | Avatar quebrado em todas as páginas           | [NAV] `img` do avatar com `naturalWidth = 0`; URL `https://usedidache.com.br/storage/avatars/…`; `curl` → "Could not resolve host". O mesmo arquivo pelo caminho relativo responde 200. [CÓD] `config/filesystems.php:45` monta a URL com `APP_URL`.                                         | Toda tela mostra um círculo vazio no lugar da foto; parece app quebrado.             | URL do disco público relativa (`/storage`), que funciona em qualquer domínio. **E, fora do código:** `APP_URL` de produção aponta para um domínio sem DNS — qualquer link absoluto gerado fora de uma requisição (push, e-mail, mensagens) também sai quebrado. Conferir DNS ou voltar `APP_URL`. | **C** | baixo   |
| T2  | Contagem de leituras diferente entre telas    | [NAV] Início: "1 de 6 leituras"; Leituras da semana: "1 de 6 leituras da semana" com **7** itens; Lição: "1 de 7 leituras feitas". [CÓD] `StudyWeekQuery.php:119` conta só os 6 dias antes do domingo (regra de negócio, coberta por `ReadingWeekTest`); `lessons/show.tsx:330` conta todas. | O aluno lê 7 e o app diz 6; parece erro.                                             | Manter a regra, nomear a unidade: no Início e em Leituras falar em **dias** ("1 de 6 dias de leitura").                                                                                                                                                                                           | **A** | baixo   |
| T3  | Abertura (splash) com versículo a cada sessão | [NAV] ~2–3 s de tela preta com versículo antes do Início. [CÓD] `splash.blade.php` usa `sessionStorage`.                                                                                                                                                                                     | Usuário recorrente espera toda vez que abre o app (nova sessão = nova espera). [HIP] | Mostrar no máx. 1×/dia ou só no app instalado; nunca bloquear >1 s.                                                                                                                                                                                                                               | M     | baixo   |
| T4  | Selos com emoji                               | [NAV] Meu progresso. [CÓD] `Badge::emoji()`, `badge-shelf.tsx:102`.                                                                                                                                                                                                                          | Inconsistência visual; emoji varia por sistema; leitor de tela lê o nome do emoji.   | Ícones Lucide por selo, com a mesma linguagem do resto.                                                                                                                                                                                                                                           | M     | baixo   |

### 3.2 Gestão — Painel

| #   | Problema                                                        | Evidência                                                                                                                                                                                   | Impacto                                                                                   | Proposta                                                                                                                                             | Prio  | Esforço |
| --- | --------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------- | ----- | ------- |
| P1  | "Próximas lições" mistura aulas marcadas com rascunhos sem data | [NAV] 3 aulas datadas + 5 rascunhos de outra série (Lição 19, 8, 10, 21, 16 — fora de ordem) com selo "SEM / —". [CÓD] `DashboardController.php:43-54` junta os dois e ordena só pela data. | O professor procura "o que vem aí" e encontra lixo de backlog; "SEM" lê-se como "semana". | Separar: **Próximas lições** só com data; **Rascunhos sem domingo** em bloco compacto, em ordem de série e número, com atalho para a lista filtrada. | **A** | baixo   |
| P2  | "Programas" e "Dados da igreja" escondidos                      | [NAV] Programas só via botão dentro de Classes; Dados da igreja só pela setinha do nome da igreja.                                                                                          | Funções raras, mas impossíveis de achar quando precisa.                                   | Médio prazo: item "Igreja" (dados, programas, fuso) no menu da conta da gestão.                                                                      | B     | médio   |

### 3.3 Gestão — Classe

| #   | Problema                                 | Evidência                                                                                                                                                                                             | Impacto                                                                          | Proposta                                                                                                   | Prio  | Esforço |
| --- | ---------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------- | ----- | ------- |
| C1  | Série dos números ≠ série em estudo      | [NAV] Cabeçalho "14 alunos · Milagres de Jesus", "Frequência na série Milagres de Jesus", enquanto o próximo domingo é de Efésios. [CÓD] `Period::started()` usa a série anterior até a nova começar. | Professor acha que a classe está "na série errada".                              | Cabeçalho só com nº de alunos; nos números, dizer quando é a série anterior ("Série anterior: …").         | **A** | baixo   |
| C2  | "Precisam de atenção" amplo demais       | [NAV] 9 de 14; inclui quem tem 4 de 4 presenças.                                                                                                                                                      | Fadiga de alerta; o realmente ausente se perde. [HIP]                            | Ordenar por gravidade (faltas antes de leitura) e separar "faltando" de "sem leitura". Decisão de produto. | M     | médio   |
| C3  | "Planejado" em todos os domingos futuros | [NAV] 12 linhas "Planejado", inclusive "Lição a definir".                                                                                                                                             | Selo sem informação (a seção já se chama "Próximos"); compete com o que importa. | Mostrar selo só quando informa: "Sem EBD".                                                                 | M     | baixo   |
| C4  | Seletor "Lição do dia" sem grupos        | [NAV] ~30 opções de 3 séries intercaladas por número. [CÓD] `ClassMeetingController::lessonOptions`.                                                                                                  | Fácil escolher a "Lição 1" errada.                                               | `<optgroup>` por série, série da lição atual primeiro, "Sem série" no fim.                                 | **A** | baixo   |
| C5  | "A lição continua no próximo" ambíguo    | [NAV] Página do domingo, "Outras ações".                                                                                                                                                              | Não diz o efeito (repete a lição no domingo seguinte).                           | Texto "Repetir esta lição no próximo domingo".                                                             | B     | baixo   |
| C6  | "Já tem conta" como rótulo de botão      | [NAV] Alunos.                                                                                                                                                                                         | Pergunta, não ação.                                                              | "Adicionar por e-mail".                                                                                    | B     | baixo   |
| C7  | Abas da classe cortadas no celular       | [LOCAL] "Relatório" fora da tela a 390 px, sem indício de rolagem.                                                                                                                                    | Aba escondida.                                                                   | Já rola; adicionar degradê na borda.                                                                       | B     | baixo   |

### 3.4 Gestão — Lições e Séries

| #   | Problema                           | Evidência                                                                                                                                         | Impacto                                               | Proposta                                                                   | Prio  | Esforço |
| --- | ---------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------- | -------------------------------------------------------------------------- | ----- | ------- |
| L1  | Título espremido no celular        | [LOCAL] 360 px/fonte 125 %: "É Necessá…", "A Cura do…" — coluna do título com ~50 px por causa do selo à direita.                                 | Lista ilegível justamente para quem usa fonte grande. | No celular, selo vai para a linha de baixo; à direita só a partir de `sm`. | **A** | baixo   |
| L2  | Ordem das séries                   | [NAV] "Fora de contexto" (24 rascunhos) antes de "Efésios" (em curso). [CÓD] `LessonController.php:64-66` ordena por `starts_on DESC NULLS LAST`. | A série ativa não é a primeira.                       | Ordenar pela próxima data de domingo da série.                             | M     | médio   |
| L3  | Abas da edição cortadas no desktop | [NAV] "Materiais" cortado a 1568 px (coluna de 672 px).                                                                                           | Aba escondida sem indício.                            | Mesmo tratamento de C7.                                                    | B     | baixo   |

### 3.5 Cabeçalho da gestão

| #   | Problema                                | Evidência                                                                                                                      | Impacto                                | Proposta                                                                                | Prio  | Esforço |
| --- | --------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------ | -------------------------------------- | --------------------------------------------------------------------------------------- | ----- | ------- |
| H1  | Nome da igreja por baixo do botão "App" | [LOCAL] 360 e 390 px: texto continua sob o botão. [CÓD] `admin-layout.tsx` usa `max-w-[11rem]` fixo, que ignora o espaço real. | Sobreposição visível; parece quebrado. | Largura máxima relativa ao espaço disponível (`max-w-full`), truncando com reticências. | **A** | baixo   |

### 3.6 App do aluno

| #   | Problema                                   | Evidência                                                                        | Impacto                                                  | Proposta                                                              | Prio | Esforço |
| --- | ------------------------------------------ | -------------------------------------------------------------------------------- | -------------------------------------------------------- | --------------------------------------------------------------------- | ---- | ------- |
| A1  | Início redundante                          | [NAV] "Abrir a lição" + atalho "Lição"; "Todas as leituras" + atalho "Leituras". | Seis atalhos, dois repetem o que está logo acima.        | Tirar "Lição" e "Leituras" dos atalhos (ficam 4). Decisão de produto. | M    | baixo   |
| A2  | Biblioteca com filtros inúteis             | [NAV] Classe/Série/Ano com 1 opção cada na igreja real (6 aulas).                | 3 controles que não filtram nada.                        | Esconder filtro com uma opção só (mantendo se estiver ativo).         | M    | baixo   |
| A3  | Biblioteca lista aulas futuras primeiro    | [NAV] 8/nov e 1/nov no topo de "guardadas para consultar".                       | Expectativa de arquivo; mostra o que nem foi dado. [HIP] | Separar "Próximas" de "Já estudadas" ou começar pela atual.           | B    | médio   |
| A4  | Barra de seções da lição corta "Anotações" | [NAV] Desktop: "A…" no fim da barra fixa.                                        | Seção escondida.                                         | Mesmo tratamento de C7.                                               | B    | baixo   |

---

## 4. Plano de simplificação

**Remover:** selo "Planejado" repetido (C3); filtros de uma opção (A2); rascunhos da lista "Próximas lições" (P1); série do cabeçalho da classe (C1); atalhos duplicados do Início (A1, a decidir).
**Consolidar:** configurações da igreja num lugar só (P2); "Dúvidas" (aba com pouco conteúdo) pode virar seção do Resumo quando houver dúvidas, liberando uma aba.
**Reorganizar:** seletor de lição por série (C4); séries pela próxima data (L2); "Precisam de atenção" por gravidade (C2).
**Tornar explícito:** unidade das contagens (T2); período dos números (C1); efeito de botões (C5, C6).

## 5. Plano de evolução visual

1. **Selos e status com menos cores.** Hoje há âmbar (rascunho), verde (publicada), azul (planejado), rosa (sem EBD), cinza (realizado). Mostrar selo só quando ele muda a decisão (rascunho, sem EBD) e deixar o resto implícito pela seção.
2. **Ícones de traço em tudo** (Lucide), incluindo selos de progresso — sem emoji, coerente com o guia "moderno e chapado".
3. **Listas com hierarquia por linha, não por coluna, no celular**: título ocupa a largura; metadados e selos embaixo (L1). Vale para Painel, Lições e Domingos.
4. **Barras horizontais roláveis com indício** (degradê nas bordas) num componente só, reaproveitado em abas da classe, abas da edição e seções da lição.
5. **Rótulos verbais** em botões ("Adicionar por e-mail", "Repetir no próximo domingo").

---

## 6. Implementado nesta rodada

| Item   | O que mudou                                                                                                                                                                          | Arquivos                                                                                                                                      |
| ------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | --------------------------------------------------------------------------------------------------------------------------------------------- |
| T1     | URL do disco público relativa (`/storage`): a foto abre em qualquer domínio que sirva o app.                                                                                         | `config/filesystems.php`                                                                                                                      |
| H1     | Nome da igreja no topo da gestão trunca no espaço disponível em vez de largura fixa de 11 rem.                                                                                       | `resources/js/layouts/admin-layout.tsx`                                                                                                       |
| L1     | Na lista de Lições, o selo vai para baixo do título no celular; à direita só a partir de 640 px. Mesmo padrão no Painel.                                                             | `pages/admin/lessons/index.tsx`, `pages/admin/dashboard.tsx`                                                                                  |
| P1     | Painel separa **Próximas lições** (só com domingo) de **Sem domingo marcado** (com link para os rascunhos); o selo "SEM / —" saiu; as sem data vêm em ordem de série e número.       | `pages/admin/dashboard.tsx`, `DashboardController.php`                                                                                        |
| C1     | Cabeçalho da classe mostra só o nº de alunos; números e lista de alunos dizem "na série anterior, X" quando a próxima lição já é de outra série.                                     | `Period::isPreviousTo()`, `ClassroomOverviewQuery.php`, `StudentController.php`, `lib/period.ts`, `classrooms/show.tsx`, `students/index.tsx` |
| C3     | Agenda mostra selo só para "Sem EBD"; "Planejado" e "Realizado" já estão ditos pela seção.                                                                                           | `classrooms/meetings/index.tsx`                                                                                                               |
| C4     | "Lição do dia" agrupada por série (`<optgroup>`): série da lição atual primeiro, avulsas no fim.                                                                                     | `ClassMeetingController::lessonOptions`, `meetings/show.tsx`, `types/ebd.ts`                                                                  |
| T2     | Início, atalho e Leituras da semana falam em **dias de leitura** ("1 de 6 dias de leitura antes da aula"), que é o que a regra conta.                                                | `week-summary.tsx`, `my-week.tsx`, `home.tsx`                                                                                                 |
| T4     | Selos com ícones Lucide em quadro de cor do tema (sem emoji).                                                                                                                        | `components/progress/badge-shelf.tsx`                                                                                                         |
| A2     | Biblioteca esconde o filtro de Classe quando há uma classe só (a menos que esteja em uso). Série e ano ficam: também separam lições avulsas e sem data (achado da revisão do Codex). | `pages/library/index.tsx`                                                                                                                     |
| C5, C6 | "Repetir a lição no próximo" e "Adicionar por e-mail" (botões e ajuda).                                                                                                              | `meetings/show.tsx`, `add-by-email-dialog.tsx`, `help.tsx`                                                                                    |

Nenhuma regra de negócio mudou: a contagem de 6 dias, o período dos números e a ordem da agenda continuam os mesmos; só a forma de mostrar.

## 7. Validação

- **Revisão independente (Codex):** apontou que esconder Série/Ano com uma opção impediria separar lições avulsas e sem data; corrigido.
- **Testes:** `php artisan test` → 569 passaram, 1 pulado (o mesmo de antes). `vp check` sem avisos, `tsc --noEmit` limpo, `npm run build` ok.
- **Navegador (local, Playwright):** 10 rotas × 3 larguras (360 px com fonte 125 %, 390, 768) antes e depois: nenhum estouro horizontal, nenhum erro de console, nenhuma requisição com erro.
    - Lições a 360 px: antes "É Necessá…" / depois título inteiro com selo embaixo.
    - Topo da gestão a 390 px: antes o nome passava por baixo de "App" / depois "Segunda I…".
    - Painel com uma série extra de rascunhos (criada só no banco descartável): seções "Próximas lições" e "Sem domingo marcado", rascunhos em ordem 1, 2.
    - Seletor do domingo: grupos "Jornada dos Milagres de Jesus (7)" e "Série de teste B (2)".
    - Biblioteca, Alunos e Meu progresso conferidos por DOM e captura.
- **Produção (2026-10-20, `https://usedidache.com.br`, Chrome do Simon, só leitura):** PR #51 em produção.
    - Avatar carrega (`naturalWidth > 0`).
    - Painel: "Próximas lições" só com as 3 aulas datadas; "Sem domingo marcado" com os rascunhos em ordem (Lição 1, 2, 3…).
    - Classe Jovens: cabeçalho "14 alunos"; números "na série anterior, Milagres de Jesus".
    - Domingos: nenhum selo "Planejado".
    - Seletor do domingo: 5 grupos por série, Efésios primeiro.
    - Início: "2 de 6 dias de leitura" e "2 de 6 dias lidos".
    - Meu progresso: selos com ícones; nenhum erro de console.
- **Não coberto:** a frase "série anterior" depende de uma semana de troca de série; foi verificada pela lógica (`isPreviousTo`) e pela tela da produção em que o caso existe hoje, não por teste automatizado novo.

## 8. Próximos passos

Por impacto ÷ esforço:

1. ~~DNS de `usedidache.com.br`~~: resolvido em 2026-10-20 (o domínio entrou no ar no mesmo dia da auditoria). A URL relativa do avatar continua valendo para qualquer domínio.
    - Dado: uma série se chama "Fora de COntexto - Volume 3" (C maiúsculo a mais), visível no seletor do domingo.
2. **C2** "Precisam de atenção": separar faltas de leitura e ordenar por gravidade (decisão de produto).
3. **L2** ordem das séries pela próxima data de domingo.
4. **A1** tirar os atalhos "Lição" e "Leituras" do Início (decisão de produto).
5. **C7/L3/A4** componente único de barra rolável com degradê nas bordas.
6. **T3** abertura no máximo 1×/dia.
7. **P2** "Igreja" (dados, programas, fuso) no menu da conta da gestão.
8. **A3** Biblioteca: separar próximas de já estudadas.
