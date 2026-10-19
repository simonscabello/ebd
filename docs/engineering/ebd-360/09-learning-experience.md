# 09 — Experiência de aprendizagem (EBD 360)

Leitura do código com olhar de **learning experience design**. O que está aqui não muda nenhuma interpretação teológica: trata só de **como** o app ajuda a pessoa a se preparar, estudar, lembrar e conversar sobre a lição.

Convenções:

- **[OBS]** comportamento que decorre do código, com `caminho:linha`.
- **[HIP]** hipótese a validar com alunos e professores.
- **PROPOSTA** ideia para discutir, **não** requisito.

Princípio que guia a análise: **atividade no app não é aprendizagem.** Marcar "lido", manter sequência e ganhar selo mostram que a pessoa _abriu_ o conteúdo. Não mostram que ela entendeu, lembrou ou mudou alguma coisa. O app hoje mede bem a atividade e quase nada além disso, o que é aceitável, desde que nenhuma decisão pastoral seja tomada como se medisse a aprendizagem.

---

## 1. O ciclo de estudo como o código o desenha

```
Publicação (professor) ─► Semana (seg–sáb) ─────────────► Domingo ──────► Depois
  aviso no celular         leitura do dia + "Para hoje"     Modo Domingo     Biblioteca
  (LessonStatusController) anotação · dúvida IA · áudio     chamada          Meu progresso
                           sequência/selos                  "onde paramos"   (sem revisão)
```

| Etapa               | Onde                                                                                                                                                                                                                                       | O que o aluno faz                                              |
| ------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | -------------------------------------------------------------- |
| Achar a lição certa | `resources/js/pages/home.tsx:146-160` (lição da classe atual), escolha de classe `home.tsx:92-122`                                                                                                                                         | Abre o Início; a lição da semana já está lá                    |
| Preparar-se         | Leituras por dia (`resources/js/components/week/week-summary.tsx`, `resources/js/pages/my-week.tsx`), "Para hoje" (`resources/js/components/week/daily-blocks.tsx`), conteúdo distribuído seg–sáb (`app/Queries/StudyWeekQuery.php:26-27`) | Lê um trecho por dia, marca como lido                          |
| Estudar             | Página da lição: texto base, estudo, aprofunde, curiosidades, conceitos (`resources/js/pages/lessons/show.tsx:409-510`)                                                                                                                    | Lê ou ouve (`resources/js/components/lesson/lesson-audio.tsx`) |
| Anotar              | `resources/js/components/lesson/personal-note.tsx`                                                                                                                                                                                         | Escreve, salva sozinho                                         |
| Tirar dúvida        | `resources/js/components/lesson/study-helper.tsx`                                                                                                                                                                                          | Pergunta à IA (limite diário)                                  |
| Domingo             | `resources/js/pages/lessons/sunday.tsx`                                                                                                                                                                                                    | Acompanha título, texto, tópicos                               |
| Depois              | Biblioteca (`resources/js/pages/library/index.tsx`), Meu progresso (`resources/js/pages/my-progress.tsx`)                                                                                                                                  | Procura aulas antigas                                          |

### Pontos fortes pedagógicos (manter)

- **Prática distribuída**: uma leitura por dia e conteúdo do dia liberado aos poucos (`StudyWeekQuery.php:26-27`; `daily-blocks.tsx:9-12`) é bom desenho — espalha o contato com o tema pela semana em vez de concentrar no sábado à noite.
- **Flexibilidade**: qualquer dia pode ser marcado a qualquer momento ("adiantar ou pôr em dia", `resources/js/components/lesson/reading-plan.tsx:16-22`, `my-week.tsx:178-180`). Não pune quem atrasou.
- **Privacidade explícita** da anotação e do progresso ("Só você vê", `personal-note.tsx:57`; `my-progress.tsx:47`).
- **IA com humildade**: "A IA pode errar e não substitui o professor nem a aula. Confira na Bíblia e leve a dúvida para domingo." (`study-helper.tsx:173-176`). Limite diário evita que a IA substitua o estudo (`study-helper.tsx:225-229`).
- **Pergunta-convite na anotação**: o placeholder "O que Deus falou com você nesta lição? Dúvidas para levar no domingo…" (`personal-note.tsx:43`) já é uma pergunta reflexiva.
- **Dúvidas da turma para o professor, anônimas** (`resources/js/pages/admin/classrooms/questions.tsx:53-58`): o professor chega no domingo sabendo o que ficou confuso. É o recurso mais pedagógico do app.
- **"Onde paramos"** entre domingos (`resources/js/components/lesson/finish-meeting-dialog.tsx:68-77`; `resources/js/pages/admin/classrooms/meetings/show.tsx:246-256`): continuidade para o professor.
- **Áudio** para quem tem baixa visão ou lê com dificuldade, com retomada de onde parou (`lesson-audio.tsx:173-189`).
- **Selos e sequência privados**, sem ranking ("Discreta: é só da própria pessoa", `resources/js/components/progress/streak-flame.tsx:6-8`; `resources/js/hooks/use-flash-toast.ts:16`). Nenhuma comparação entre alunos. Isso deve continuar assim.

---

## 2. Achados por dimensão

### 2.1 Como o aluno se prepara e acha a lição certa

- [OBS] A lição da semana aparece sozinha no Início, com contagem até domingo (`home.tsx:262-276`). Boa redução de esforço.
- [OBS] O atalho "Lição" pode abrir o PDF da revista fora do app (`home.tsx:319-331`), e o estudo transcrito no app fica a um toque de distância por outro caminho (o cartão da lição). [HIP] Dois "lugares da lição" confundem quem está começando; parte das pessoas pode ficar só no PDF e não ver leituras, conceitos e anotação.
- [OBS] Quando a lição ocupa dois domingos, o app avisa "Encontro 1 de 2" (`home.tsx:153-156`) — ajuda a entender que não é lição nova.

### 2.2 Leituras e progresso

- [OBS] "Marcar como lido" fica no rodapé do leitor (`resources/js/components/lesson/reading-sheet.tsx:69-73`), depois do texto, e na lista só existe "Ler o texto" (`reading-plan.tsx:182-194`): para marcar, a pessoa passa pelo texto. Bom desenho.
- [OBS] Progresso = dias marcados / dias do plano (`week-summary.tsx:47-51`, `my-progress.tsx:113-131`). É contagem de **marcações**, não de leitura nem de compreensão.
- [OBS] Sequência: "Leia hoje para manter a sequência." (`streak-flame.tsx:32`) e selos "7 dias seguidos", "30 dias seguidos", "Presença em todos os domingos" (`app/Enums/Badge.php:19-23`).
    - [HIP] Para idosos e para quem está doente, viajando ou cuidando de alguém, "Presença em todos os domingos" e "30 dias seguidos" podem gerar sentimento de falha. Não é competitivo (é privado), mas pode ser constrangedor consigo mesmo.
    - [HIP] Quem lê na Bíblia de papel e não marca no app aparece com sequência zero — e também como "precisa de atenção" para o professor (ver 2.6).

### 2.3 Anotações

- [OBS] Uma anotação livre por lição (`personal-note.tsx:17-61`). Não há pergunta-guia além do placeholder, que some quando a pessoa começa a escrever.
- [OBS] A anotação **não volta** em nenhum outro momento: não aparece no Modo Domingo (`sunday.tsx` não recebe `study`), nem na Biblioteca, nem em "Meu progresso". O placeholder sugere levar dúvidas para domingo (`personal-note.tsx:43`), mas o domingo não as mostra.
- [OBS] Resposta da IA não pode ser guardada na anotação (`study-helper.tsx:32-36`).

### 2.4 Revisitar

- [OBS] Biblioteca com busca de texto inteiro e destaque do trecho (`library/index.tsx:257-266`) — ótima para "onde estava aquele assunto?".
- [OBS] Não há **revisão**: nenhuma retomada da lição anterior no começo da semana seguinte, nenhuma pergunta "lembra do que vimos?", nenhuma lista de "minhas anotações".
- [OBS] "Lição a lição" em Meu progresso mostra barras de leitura e presença (`my-progress.tsx:92-160`), não conteúdo. É um histórico de atividade.

### 2.5 Perguntas reflexivas e recuperação ativa

- [OBS] O modelo de blocos tem Roteiro, Se houver tempo, Nota de precisão, Notas do professor, Contexto, Teologia, Curiosidade, Aplicação prática, Conceito (`app/Enums/LessonBlockKind.php:11-32`). **Não há um tipo "Pergunta para reflexão" ou "Para conversar"** voltado ao aluno. Perguntas, quando existem, ficam diluídas no texto do estudo ou no roteiro do professor.
- [OBS] Nada no app pede que a pessoa **recupere** algo de memória (responder antes de reler, resumir com as próprias palavras). A IA tem a sugestão "Resuma o estudo em três pontos" (`study-helper.tsx:24`) — mas quem resume é a IA, não o aluno. Do ponto de vista de aprendizagem, isso é o inverso da recuperação ativa.
- [HIP] Pedir ao aluno "escreva em uma frase o que ficou para você" antes de mostrar o resumo da IA pode ter mais efeito do que a sugestão atual. Tem que ser opcional e sem correção.

### 2.6 Como o professor conduz e acompanha

- [OBS] Modo Domingo para o professor junta roteiro, notas de precisão, "se houver tempo", chamada e encerramento numa tela (`sunday.tsx:189-247`, `276-322`). Muito bom.
- [OBS] O professor vê **quantos leram** a lição atual ("N de M leram a lição atual", `resources/js/pages/admin/classrooms/show.tsx:303-312`) e quem está "sem leitura" (`app/Queries/StudentsNeedingAttention.php:43-50`). Isso é **marcação no app**, apresentada com o verbo "ler".
- [OBS] A mensagem pronta para quem está "em atenção" é sempre "Sentimos sua falta na EBD" (`resources/js/lib/whatsapp.ts:16`), mesmo para quem só não marcou leitura e esteve presente. Risco de constrangimento e de a pessoa sentir que está sendo vigiada.
- [OBS] O aluno não sabe que suas perguntas à IA aparecem (anônimas) para o professor (`study-helper.tsx:117` não diz). Em classes pequenas, "1 pessoa perguntou" (`questions.tsx:20-21`, campo `people`) pode identificar alguém.

### 2.7 Continuidade pós-domingo

- [OBS] Depois do domingo, a lição some do Início e entra a próxima (`home.tsx:146-160`, "Última aula" quando não há próxima). A anterior vira item em "Aulas anteriores" (`home.tsx:180-219`).
- [OBS] O "onde paramos" é só para professores (`finish-meeting-dialog.tsx:68`). O aluno que faltou não tem um resumo do que foi visto.
- [OBS] Quando a lição continua no próximo domingo, o aluno vê "Encontro 2 de 2", mas nada sobre onde a turma parou.

---

## 3. Riscos de interpretação (atividade ≠ aprendizagem)

| Sinal no app                                                | O que mede de fato            | Leitura errada a evitar                                               |
| ----------------------------------------------------------- | ----------------------------- | --------------------------------------------------------------------- |
| Leitura marcada (`reading_checkins`)                        | Tocou "Marcar como lido"      | "Leu e entendeu"                                                      |
| Sequência / selos                                           | Constância de marcação        | "Está crescendo espiritualmente"                                      |
| "N de M leram" (`classrooms/show.tsx:310`)                  | Quantos marcaram ≥1 dia       | "A turma se preparou"                                                 |
| "Sem leitura há N dias" (`StudentsNeedingAttention.php:49`) | Não marcou no app             | "Está afastado"                                                       |
| Perguntas à IA                                              | Curiosidade/dúvida registrada | "Quem pergunta mais aprende mais"                                     |
| Presença                                                    | Esteve no domingo             | É o sinal mais confiável de vínculo, e mesmo assim não é aprendizagem |

Recomendação: trocar, na gestão, o verbo **"leram"** por **"marcaram leitura no app"** e separar "faltas" de "sem marcação" na lista de atenção. Mudança de texto, sem tecnologia nova.

---

## 4. PROPOSTAS (não requisitos)

Todas opcionais para o aluno, sem pontuação, sem ranking, sem notificação de cobrança.

1. **PROPOSTA — "Para pensar" (tipo de bloco do aluno).** Novo `LessonBlockKind` com 1–3 perguntas abertas por lição, liberadas na semana como os demais blocos (`StudyWeekQuery.php:26-27`). O professor escreve; nada é corrigido. Ao tocar numa pergunta, abre a anotação com a pergunta citada.
2. **PROPOSTA — "Uma frase que ficou".** Ao marcar a última leitura da semana (ou no sábado), convite opcional: "Escreva em uma frase o que ficou para você" — vai para a anotação. Recuperação ativa leve, sem prova.
3. **PROPOSTA — Revisão de abertura.** Na segunda-feira, o Início mostra por 1–2 dias um cartão "Na semana passada: {título}. Você escreveu: '…'" (só a própria anotação; se não houver, o versículo-chave). Liga uma lição à outra.
4. **PROPOSTA — Minhas anotações no Modo Domingo.** Seção recolhida "Minhas anotações" (só leitura) para membros (`sunday.tsx`), cumprindo o que o placeholder promete.
5. **PROPOSTA — Página "Minhas anotações".** Lista por lição, mais recente primeiro, com busca. Visível só para a pessoa.
6. **PROPOSTA — Guardar resposta da IA.** Botão "Guardar nas minhas anotações" em cada resposta (`study-helper.tsx:144-158`), com a marcação "(resposta da IA)".
7. **PROPOSTA — Trocar a sugestão "Resuma o estudo em três pontos"** (`study-helper.tsx:24`) por "Confira se entendi: …" (o aluno escreve o resumo e a IA comenta). Mantém o aluno pensando.
8. **PROPOSTA — Resumo para quem faltou.** O professor marca, no encerramento, se o "onde paramos" pode ser mostrado aos alunos (texto curto, opcional). Aparece no Início como "No domingo vimos…".
9. **PROPOSTA — Sequência mais gentil.** Contar **semanas** com pelo menos uma leitura em vez de dias seguidos, ou trocar "Leia hoje para manter a sequência" por "Que tal a leitura de hoje?". Repensar "Presença em todos os domingos" (ex.: "Esteve na maioria dos domingos da série").
10. **PROPOSTA — Mensagem de cuidado conforme o motivo** (`lib/whatsapp.ts:15-17`): falta → mensagem atual; só "sem marcação" → nenhuma mensagem pronta (o professor decide) ou "Separei a leitura desta semana para você: …".
11. **PROPOSTA — Transparência das dúvidas.** Uma linha no painel da IA dizendo que o professor vê as perguntas sem nomes; na aba Dúvidas, ocultar o número de pessoas quando for 1.

---

## 5. Plano de validação pedagógica

### 5.1 Perguntas de pesquisa

1. O app ajuda o aluno a **chegar ao domingo com algo a dizer** (uma pergunta, uma frase, um versículo)?
2. As leituras diárias são **lidas** ou só **marcadas**?
3. A sequência e os selos motivam, são indiferentes ou constrangem?
4. A lista "Precisam de atenção" leva a contatos que a pessoa percebe como cuidado?
5. As dúvidas da turma mudam a forma como o professor conduz o domingo?

### 5.2 O que medir, como e com quem

| O que                           | Como                                                                                                                                               | Com quem                                                       | Privacidade                                                                         |
| ------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------- | ----------------------------------------------------------------------------------- |
| Compreensão percebida           | 2 perguntas no fim da aula, em papel ou de viva voz ("O que ficou para você hoje?" / "Algo ficou confuso?")                                        | Professores de 2–3 classes, 4 domingos                         | Sem nome; o professor anota temas, não pessoas                                      |
| Preparação real                 | Professor pergunta no início: "Quem conseguiu ler algo esta semana, no app ou na Bíblia?" e compara (só no agregado) com a taxa de marcação do app | Mesmas classes                                                 | Só números da turma; nada individual sai da sala                                    |
| Experiência com sequência/selos | Entrevista de 15 min                                                                                                                               | 6–8 alunos, metade com 60+ anos, metade com selos e metade sem | Consentimento verbal; sem gravação ou com gravação apagada após transcrição anônima |
| Uso da anotação                 | Contagem agregada de lições com anotação não vazia por semana (nunca o conteúdo)                                                                   | Todo o banco                                                   | O conteúdo das anotações **não** é lido por ninguém; só `count(*)` e tamanho médio  |
| Uso das dúvidas pelo professor  | Diário curto do professor: "olhei a aba Dúvidas? mudou algo na aula?"                                                                              | 2–3 professores, 4 semanas                                     | Só do professor                                                                     |
| Primeiro acesso                 | Taxa "cadastro pendente" / "nunca entrou" (já existe em `students/index.tsx:176-182`) antes e depois de mudanças no cadastro                       | Admin                                                          | Agregado por classe                                                                 |
| Mensagens de cuidado            | Pergunta aberta a 4–6 pessoas que receberam mensagem: "Como você se sentiu?"                                                                       | Alunos contatados                                              | Feita pelo próprio professor, com tato; nada registrado no app                      |

### 5.3 Regras de respeito

- Nenhuma métrica individual de leitura vira conversa pastoral sem que a pessoa traga o assunto.
- Nada de painel comparando alunos. Nada de "quem leu mais".
- Conteúdo de anotações e o texto das dúvidas **não** entram em análise fora do que o app já mostra (dúvidas anônimas ao professor).
- Teste com usuários idosos: sessões presenciais, no celular da própria pessoa, com alguém de confiança perto; parar se houver desconforto.
- Antes de qualquer coleta nova no código (eventos, métricas), descrever na política de privacidade e na tela de Perfil.

### 5.4 Critérios para decidir as PROPOSTAS

- Uma proposta segue se, nas entrevistas, a maioria dos alunos de 60+ disser que entende **e** que não se sente cobrada.
- "Para pensar" e "Uma frase que ficou" seguem se os professores relatarem mais participação na conversa do domingo (diário do professor), não se o número de anotações subir.
- Mudanças na sequência seguem se ao menos parte dos entrevistados relatar culpa ou ansiedade com o formato atual.

---

## 6. Resumo

O app já tem um bom esqueleto pedagógico: estudo espalhado pela semana, privacidade, IA com limites e humildade, e um canal de dúvidas anônimas para o professor. O que falta é **fechar o ciclo**: fazer as anotações e as perguntas da semana voltarem no domingo e na semana seguinte, e dar ao aluno oportunidades leves de dizer com as próprias palavras o que entendeu. E, do lado do professor, parar de chamar de "leitura" o que é marcação, para que ninguém seja tratado como afastado por estudar no papel.
