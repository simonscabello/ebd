# 00 · Resumo executivo — Operação EBD 360°

**Data:** 2026-10-19 · **Base:** `main` @ `e03fd30` · **Branch de trabalho:** `engineering/ebd-360`, só local; nada publicado, nenhum deploy, produção intocada.

## Estado atual do produto

A EBD é um monólito Laravel 13 + Inertia 3/React 19 bem organizado: Actions, Queries e Policies, 379 testes e análise estática limpa. Ela entrega o que promete: lição da semana, plano de leitura, anotações, Modo Domingo com chamada, gestão por classe, links pessoais, push, PWA, "Tirar dúvida" com IA, áudio e servidor MCP (inventário completo em 03).

**A base é sólida.** O isolamento entre classes, o ponto mais sensível, foi conferido por três caminhos independentes, e nenhum achou falha: leitura de todas as rotas e das 20 ferramentas MCP (05), auditoria Laravel (04) e ~70 tentativas reais no navegador (12). Não há P0. Os problemas encontrados são localizados, e boa parte já foi corrigida aqui, com teste.

## As dez descobertas principais

| #   | Descoberta                                                                                                                                | Gravidade         | Situação                                                                 |
| --- | ----------------------------------------------------------------------------------------------------------------------------------------- | ----------------- | ------------------------------------------------------------------------ |
| 1   | Autorização entre classes, anotações pessoais e conteúdo do professor estão corretamente isolados                                         | —                 | ✅ verificado (05, 04, 12)                                               |
| 2   | Um professor pode adicionar por e-mail um aluno de outra classe e entrar na conta dele pelo link de recuperação, que apaga a senha (S-01) | P1 (05) / P2 (19) | 🔒 decisão do PO (D1)                                                    |
| 3   | Sem rede, a anotação e a chamada ficavam em "Salvando…" para sempre e o texto se perdia ao recarregar                                     | P1                | ✅ corrigido (L11 comprovado no navegador; L12, a chamada, só no código) |
| 4   | Uma inscrição push mal formada parava os lembretes de todas as classes seguintes; o envio de push não tinha timeout                       | P2                | ✅ corrigido (L1, L14)                                                   |
| 5   | Erros 500 em validação (e-mail com maiúsculas, telefone longo) e "encerrar com continua" gravando antes de dar erro                       | P2/P3             | ✅ corrigido (L2–L4)                                                     |
| 6   | "Tirar dúvida": sem P0/P1. Falha sem diagnóstico, até 3 tentativas pagas, corrida no limite diário                                        | P2/P3             | ✅ corrigido (L5); prompt e privacidade 🔒 (D4, D5)                      |
| 7   | Lição que continua no domingo seguinte: a leitura da 2ª semana não é registrada e o lembrete diz "você já leu"                            | P2/P3             | 🔒 regra de produto (D2)                                                 |
| 8   | A agenda aceita encontro em dia que não é domingo (observado: criou numa terça)                                                           | P2                | backlog P1-08                                                            |
| 9   | Áudio, push e IA rodam no processo web (fila `sync`, sem worker); há 4 definições de "encontro atual" e escrita em GET                    | P2                | ADR-003, backlog P2-10..12                                               |
| 10  | O app mede _atividade_ (marcar leitura), não _aprendizado_; anotações e dúvidas não voltam no domingo nem na revisão                      | —                 | propostas P3, validar antes (09)                                         |

## O que foi implementado (detalhe e provas em 17)

Quinze correções pequenas. L1–L6, L8, L10, L11 e L14 têm teste ou medição. L7, L9, L12, L13 e L15 foram verificadas só por leitura, `tsc` e build:

- **Push:** inscrição inválida isolada, sem reenvio duplicado; timeout de 10 s.
- **Validação:** e-mail e telefone (422 no lugar de 500); "encerrar com continua" sem lição não grava nada.
- **IA:** lock por pessoa, no máximo 2 tentativas, log do status e do código do erro sem o conteúdo, mensagem neutra.
- **Desempenho e banco:** busca de livro bíblico memoizada; 4 índices de FK (migration `CONCURRENTLY`, reexecutável); lock na chamada simultânea.
- **Testes:** proibidos de usar rede e a chave real da OpenAI.
- **Front:** autosave e chamada resilientes offline; botão "Entrar com e-mail e senha" na tela de link inválido; texto do tutorial do iPhone; 4 ajustes de acessibilidade sem mudança visual.

## Resultados dos testes

- **Baseline:** 373 testes (372 ok, 1 pulado).
- **Final:** **380 testes (379 ok, 1 pulado)**, 2.809 asserções. Pint, Larastan, `tsc`, lint e build verdes.
- **Não executado:** IA real e o conjunto de 60 casos (pago, sem autorização), concorrência real, contêiner de produção e leitor de tela. Detalhes em 18.

## Diagnóstico da IA (10)

- O contexto é só a lição como o aluno a vê. O conteúdo do professor fica fora (com teste). A resposta é exibida como texto puro e nenhum dado de identificação (nome, e-mail, anotações) vai no prompt; a dúvida, que é texto livre do aluno, vai para a OpenAI.
- Custo: ~US$0,001–0,0045 por pergunta.
- Fraquezas:
    - a lição e a pergunta vão juntas, sem delimitação (risco de injeção a partir do texto da lição);
    - a OpenAI guarda os pedidos por padrão (`store` não enviado);
    - não há medição de qualidade.
- O conjunto de avaliação com 60 casos, a rubrica e o harness estão prontos em `ai-eval/`. O harness não roda sem autorização explícita.

## Principais obstáculos de UX (08, 12)

- Falhas offline silenciosas (as de salvar foram corrigidas; marcar leitura e navegar continuam).
- Alvos de toque de 20–40 px e o estouro de `/meu-progresso` com fonte a 150%.
- "Publicar" num toque, que avisa a classe inteira e aceita lição vazia.
- Visitante em lição "só membros" cai na tela de senha.
- Cadastro de 7 campos bloqueia até a lição pública.
- A mensagem "Sentimos sua falta" pode constranger quem esteve presente.

## Recomendação arquitetural (13, 14)

**Consolidar agora (cenário A), evoluir o estudo em fatias validadas depois (B) e não preparar multi-igreja (C)** enquanto não houver demanda. Nenhuma reescrita é necessária. As únicas mudanças estruturais propostas são um worker de fila (ADR-003) e unificar o "encontro atual", esta só depois de testes de caracterização.

## Revisões adversariais (19)

1. **Diagnósticos (agente revisor).** Contestou seis achados lidos pela metade, rebaixou severidades e trouxe seis riscos novos. Dois deles já foram corrigidos (R1, R5).
2. **Implementação (Codex CLI).** Achou três problemas reais no meu primeiro lote, todos corrigidos.
3. **Proposta arquitetural e validação.**
4. **Revisão final.** Rodou os testes da branch contra o código da `main` e confirmou que os testes de L2, L4 e L5 falham lá. Achou três problemas no diff, corrigidos no lote 4: teste do telefone que não provava o 500, lock que podia ficar preso se o PHP cortasse o pedido por tempo, e log enganoso. Achou também afirmações exageradas nestes documentos, já corrigidas. As discordâncias que ficaram estão no fim do 19.

## Backlog e decisões

Backlog P1–P5 em 15, roadmap em 20. As **dez decisões que dependem do Product Owner** (D1–D10) estão em 21.
