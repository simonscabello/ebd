# Prompt — Materiais da lição no NotebookLM

> Gera, a partir do PDF da lição da revista, o conteúdo de cada campo do cadastro da lição no app da EBD, já no formato que o campo aceita.
>
> Antes de usar, troque nos textos abaixo: **[CLASSE]** (ex.: jovens) e **[DURAÇÃO]** (minutos de aula, ex.: 50).

---

## Como usar

1. **Crie um notebook para a lição** e adicione como fonte o PDF da lição da revista.
2. **Adicione de 2 a 4 fontes de apoio.** O NotebookLM responde a partir das fontes do notebook: só com a revista, o contexto histórico, a arqueologia e os termos em hebraico/grego ficam limitados ao que ela já traz. Use "Descobrir fontes" (ou a pesquisa) com os temas da lição, por exemplo "piscina de Siloé arqueologia" ou "Festa dos Tabernáculos costumes". Prefira dicionários e enciclopédias bíblicas, comentários e sites acadêmicos ou de arqueologia.
3. **Em "Configurar conversa"**, escolha **Personalizado**, cole as [Instruções gerais](#instruções-gerais) e marque a resposta **Mais longa**. Se preferir, mande as instruções como primeira mensagem do chat.
4. **Envie as partes 1 a 6, uma por vez.** Pedir tudo de uma vez faz a resposta ser cortada. Se uma resposta parar no meio, peça: _"Continue exatamente de onde parou, no mesmo bloco de código."_
5. **Copie o que está dentro de cada bloco de código** para o campo indicado. Se sobrar número de citação (ex.: `[1]`) no meio do texto, apague.
6. **No Studio**, gere os materiais (áudio, vídeo, slides...) com os prompts da última seção.

### Onde cada parte entra no app

| Parte                    | Tela da lição no app                                                                                    |
| ------------------------ | ------------------------------------------------------------------------------------------------------- |
| 1. Dados e leituras      | Formulário da lição (nº, título, texto bíblico, versículo-chave, alvo, resumo) e **Leituras da semana** |
| 2. Estudo principal      | Campo **Estudo principal**                                                                              |
| 3. Blocos do professor   | **Blocos**: Roteiro da aula, Notas do professor, Se houver tempo, Nota de precisão                      |
| 4. Blocos dos alunos     | **Blocos**: Contexto histórico e cultural, Análise teológica, Aplicação prática, Curiosidade            |
| 5. Conceitos             | **Blocos**: Conceito                                                                                    |
| 6. Referências e convite | **Materiais** (Referência bibliográfica) e o grupo do WhatsApp                                          |
| Studio                   | **Materiais**: áudio, vídeo, slides, mapa mental, manual do professor                                   |

Série, domingo da aula, endereço, visibilidade e autores são preenchidos à mão. Ao escolher o tipo do bloco, o público (alunos ou só professor) já vem certo.

---

## Instruções gerais

```text
Você é teólogo, historiador e professor de estudos bíblicos, preparando uma lição da Escola Bíblica Dominical (EBD) para a classe de [CLASSE] de uma igreja evangélica. A fonte principal é o PDF da lição da revista; as outras fontes do notebook são apoio.

Tudo o que você produzir será cadastrado num app da EBD, campo por campo. Por isso:
- Responda somente à parte pedida. Para cada campo, escreva o nome exato do campo e, logo abaixo, o conteúdo dentro de um bloco de código, para eu copiar sem perder a formatação. Não coloque números de citação dentro dos blocos de código.
- Campo "texto simples": um único parágrafo, sem Markdown, sem listas e sem quebras de linha.
- Campo "Markdown": aceita ### subtítulo, **negrito**, *itálico*, > citação e listas. Não use tabelas, HTML, emojis nem títulos com # ou ##, a não ser quando a parte pedir.
- Referências bíblicas no formato "Jo 9.1-41" (livro abreviado ou por extenso, ponto entre capítulo e versículo, ponto e vírgula entre trechos). Nos campos de referência, escreva só a referência: o app mostra o texto bíblico sozinho.

Sobre o conteúdo:
- Leia a lição inteira antes de responder (se o PDF for escaneado, leia as páginas como imagem). Respeite a ordem dos tópicos e a linha de raciocínio do autor.
- Não copie nem reescreva o texto da revista. Produza conteúdo complementar que aprofunde a lição: a página da lição pode ficar pública.
- Aprofunde com contexto histórico e cultural (costumes, cenário político e social, mentalidade dos povos, o que o autor bíblico queria comunicar ao público da época), análise teológica (atributos de Deus, doutrinas, conexões entre Antigo e Novo Testamento), curiosidades (geografia, arqueologia, termos em hebraico e grego com transliteração, jogos de palavras) e aplicação prática.
- Fidelidade bíblica e histórica. Diferencie fato, hipótese e opinião ("a tradição diz", "alguns estudiosos sugerem"). Não invente fontes, datas, números nem achados arqueológicos. Se as fontes do notebook não sustentarem algo, diga o que faltou em vez de preencher.
- Cada informação aparece em um só lugar: o estudo principal e os blocos se complementam, não se repetem. Nas partes seguintes, não repita o que já saiu nas anteriores.
- Português do Brasil, linguagem clara e acolhedora, adequada à classe de [CLASSE]. Explique qualquer termo técnico. Parágrafos curtos: quase todos leem no celular.
```

---

## Parte 1 — Dados da lição e leituras da semana

```text
Parte 1. Preencha os dados da lição e as leituras da semana.

Dados da lição:
- Nº na revista: só o número.
- Título: o título da lição como está na revista.
- Texto bíblico principal: só a referência do texto base.
- Versículo-chave: só a referência.
- Alvo da lição (texto simples): o objetivo da lição em uma frase, fiel ao que a revista propõe.
- Resumo / introdução (texto simples, até 250 caracteres): 1 ou 2 frases que despertem vontade de estudar a lição. Aparece no topo da página e na prévia do link quando compartilhado no WhatsApp. Não repita o título.

Leituras da semana: uma por dia, de segunda a domingo, seguindo a leitura diária da revista. Se a revista não trouxer, proponha leituras ligadas ao tema e avise que são sugestões suas. Para cada dia, informe:
- Dia: Segunda-feira, Terça-feira... Domingo.
- Leitura: só a referência.
- Orientação (texto simples, até 300 caracteres): uma frase ligando o texto do dia ao tema da lição e uma pergunta que comece com "Para refletir:".
```

## Parte 2 — Estudo principal

```text
Parte 2. Escreva o campo "Estudo principal" (Markdown): um estudo complementar que acompanha a lição tópico por tópico.

- Mesma estrutura da revista: introdução em um ou dois parágrafos sem título; "## I. Título do tópico" para cada tópico; "### 1. Título do subtópico" para cada subtópico; "## Conclusão" no fim. Use os títulos da revista: no app eles viram os tópicos que o professor segue durante a aula.
- Aprofunde dentro de cada tópico e subtópico, sem criar seções separadas de contexto, teologia ou curiosidades: o que o texto dizia ao público original, o contexto, a teologia, os termos do original e a aplicação.
- Conecte com as leituras da semana quando fizer sentido.
- De 1.500 a 2.500 palavras.
- Termine a conclusão com uma pergunta reflexiva profunda, em negrito, para encerrar a discussão.
- Entregue o estudo inteiro num único bloco de código.
```

## Parte 3 — Blocos do professor

```text
Parte 3. Crie os blocos que só o professor vê. Eles aparecem no Modo Domingo, a tela que o professor usa durante a aula. Para cada bloco informe Tipo, Título e Conteúdo (Markdown, num bloco de código).

1. Roteiro da aula (1 bloco, título "Roteiro da aula"): plano para [DURAÇÃO] minutos em lista numerada, com o tempo de cada etapa: abertura com uma pergunta ou situação que prenda a atenção; leitura do texto base; cada tópico da revista com a ideia central em uma frase e uma pergunta para a classe; discussão com as perguntas dos boxes da revista, se houver; conclusão com a pergunta reflexiva do estudo.
2. Notas do professor (1 bloco, título "Para se preparar"): perguntas difíceis que a classe pode fazer sobre o tema, com respostas curtas; pontos sensíveis que pedem cuidado pastoral; ilustrações que ajudam a explicar.
3. Se houver tempo (2 ou 3 blocos, um assunto por bloco, 60 a 150 palavras cada): aprofundamentos curtos para usar se sobrar tempo. Título descritivo, por exemplo: "Saliva como remédio no mundo antigo".
4. Nota de precisão (0 a 3 blocos, 60 a 150 palavras cada): só quando a revista trouxer uma afirmação especulativa, imprecisa ou discutível. Em cada uma: o que a revista diz, o que se sabe de fato segundo as fontes e como tratar o assunto em sala. Se não houver nada a corrigir, diga isso e não crie o bloco.
```

## Parte 4 — Blocos dos alunos

```text
Parte 4. Crie os blocos para os alunos. Eles aparecem na página da lição (seções "Aprofunde" e "Curiosidades") e alguns são liberados um por dia em "Minha semana", o plano de estudo do aluno até domingo. Para cada bloco informe Tipo, Título, Liberar em "Minha semana" (o dia, ou "não liberar") e Conteúdo (Markdown, num bloco de código).

Título: uma frase curta e concreta que já conte a novidade (ex.: "A piscina de Siloé foi encontrada em 2004"), nunca o nome do tipo. Não comece o conteúdo repetindo o título.

1. Contexto histórico e cultural (2 ou 3 blocos, 100 a 200 palavras cada, não liberar): costumes, cenário político e social e mentalidade da época que iluminam a lição.
2. Análise teológica (1 ou 2 blocos, 100 a 200 palavras cada, não liberar): o que a passagem revela sobre Deus, sobre Cristo e sobre a salvação; conexões entre Antigo e Novo Testamento.
3. Aplicação prática (2 blocos, 80 a 150 palavras cada):
   - "Desafio da semana": uma ação concreta, possível para alguém da classe de [CLASSE], ligada ao alvo da lição. Liberar na segunda-feira.
   - "Guarde no coração": uma dica para memorizar o versículo-chave (sem copiar o texto do versículo, que o app já mostra). Liberar na terça-feira.
4. Curiosidade (6 blocos, 40 a 90 palavras cada): os fatos mais marcantes da lição (geografia, arqueologia, palavras do hebraico e do grego com transliteração, costumes), em linguagem simples, no espírito de "Você sabia?". Um por dia, de segunda a sábado, de preferência ligando a curiosidade à leitura daquele dia.
```

## Parte 5 — Conceitos

```text
Parte 5. Escolha de 3 a 5 conceitos citados na lição que mereçam um ensaio próprio (exemplos de lições anteriores: a Festa dos Tabernáculos e as 39 categorias de trabalho proibido no sábado; o centurião e o exército romano; o soreg do templo; os cananeus e a Fenícia; o banquete messiânico). Crie um bloco do tipo Conceito para cada um:

- Título: o nome do conceito.
- Conteúdo (Markdown, 250 a 500 palavras): prosa corrida com história, arqueologia quando houver e referências bíblicas, terminando com um fechamento que aponte para Cristo. Pode usar ### para dividir em partes.
- Liberar em "Minha semana": distribua os conceitos entre quarta-feira e sábado, um por dia (segunda e terça já têm o desafio e a dica de memorização).

Não repita o que já está no estudo, no contexto ou nas curiosidades.
```

## Parte 6 — Referências e convite para o WhatsApp

```text
Parte 6.

1. Referências: liste as fontes deste notebook que você usou nas respostas, exceto a revista. Para cada uma informe Título (título da obra ou da página, com o autor, se houver) e Descrição (texto simples, uma frase dizendo o que ela acrescenta à lição). Não cite nada que não esteja entre as fontes.

2. Convite: escreva um texto curto e bem descontraído para o grupo da EBD da classe de [CLASSE] (4 a 6 linhas, com emojis e humor leve), chamando a turma para estudar a lição durante a semana pelo app e para a aula de domingo, e citando o versículo para memorizar. Se eu disser que a semana já começou, brinque com isso e oriente a colocar a leitura em dia. Não inclua links nem a lista de leituras: o app gera uma mensagem com isso, e o convite vai logo antes dela.
```

---

## Studio — materiais da lição

Cada item vira um **Material** da lição no app. Em cada geração do Studio, use **Personalizar** e cole o texto indicado.

### Resumo em áudio: podcast da semana

- **No app:** material do tipo **Áudio**, público **Alunos**. O app aceita MP3, M4A, OGG e WAV até 60 MB. Se o arquivo passar disso, converta para MP3 ou publique em algum lugar e use o link.
- **No NotebookLM:** formato **Análise detalhada**, idioma português.

```text
Episódio para a classe de [CLASSE] ouvir durante a semana, em português do Brasil. Conversem sobre a lição seguindo a ordem dos tópicos da revista, trazendo o contexto histórico, as curiosidades mais marcantes e a aplicação para a vida de hoje. Terminem com o desafio da semana e a pergunta reflexiva final. Tom acolhedor e animado, sem sensacionalismo; não apresentem hipóteses como fatos.
```

### Resumo em vídeo

- **No app:** material do tipo **Vídeo**, público **Alunos**. O tipo Vídeo pede um link: publique no YouTube como "não listado" (ou no Google Drive, com acesso por link) e cole o endereço.

```text
Vídeo curto para a classe de [CLASSE], em português do Brasil, explicando a ideia central da lição, o contexto que ajuda a entendê-la e uma aplicação prática. Visual limpo, pouco texto na tela.
```

### Apresentação de slides: para a turma acompanhar na aula

- **No app:** material do tipo **PDF** (ou **Arquivo**, se baixar em PPTX), público **Alunos**.
- Confira antes de enviar: texto estourado, erros de digitação e referências.

```text
Slides em português do Brasil para a turma acompanhar a aula, em 16:9, com cerca de 18 slides e pouco texto em cada um: os slides são apoio visual, o aprofundamento fica no estudo. Estrutura: capa; versículo-chave e alvo da lição; introdução; um slide divisório para cada seção da revista; slides dos pontos e subtópicos; slide de discussão com as perguntas dos boxes da revista; conclusão; slide final escuro com a pergunta reflexiva. Estilo: azul-marinho (#1E2761) dominante com dourado (#C9A227) de acento, títulos em fonte serifada e corpo em fonte sem serifa, capa e divisórias escuras, slides de conteúdo claros, ícones em círculos coloridos, números grandes em destaque e as palavras-chave do grego e do hebraico.
```

### Mapa mental (opcional)

- **No app:** baixe a imagem e envie como material do tipo **Arquivo** (PNG), público **Alunos**. É um bom resumo visual para quem faltou.

### Manual do professor (opcional)

Documento para ler fora do app ou imprimir, com tudo o que é do professor.

- **No NotebookLM:** Relatório → **Criar o seu** (formato personalizado).
- **No app:** exporte para o Google Docs, baixe como PDF e envie como material do tipo **PDF**, público **Só professor**.

```text
Manual do professor para a lição, em português do Brasil. Junte, na ordem dos tópicos da revista: o roteiro da aula para [DURAÇÃO] minutos, o estudo complementar de cada tópico, as notas de precisão, os aprofundamentos "se houver tempo", as perguntas difíceis que a classe pode fazer (com respostas) e a pergunta reflexiva final. Não copie o texto da revista.
```
