# 21 · Próximos passos

## Decisões que dependem do Product Owner

| #   | Decisão                                                                                                                                                  | Opções                                                      | Recomendação técnica                                                                                                                                                                                           | Onde           |
| --- | -------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------- |
| D1  | Professor adiciona por e-mail qualquer conta e depois emite link de recuperação para ela (S-01; também bloquear ou revogar aluno de outra classe, R4)    | ADR-007, opções 1–4                                         | Bloquear "adicionar por e-mail" de quem já é aluno de outra classe que o professor não gerencia, e registrar em AuditLog. Severidade discutida: P1 (05) × P2 (19); exige ser professor nomeado e deixa rastro. | 05, 14, 19     |
| D2  | Leitura na 2ª semana de lição que continua: conta como leitura nova? o lembrete "você já leu hoje"?                                                      | manter, recomeçar a semana ou tratar como semana de revisão | Escolher a regra antes de mexer no código, que hoje está inconsistente (04 A2/B1)                                                                                                                              | 04, 15 P1-04   |
| D3  | Endpoint de push restrito a serviços conhecidos (S-02)                                                                                                   | lista de hosts / só https / deixar                          | lista de hosts + https                                                                                                                                                                                         | 05, 15 P1-10   |
| D4  | Privacidade da IA: `store=false`; aviso de que o professor vê as dúvidas (anônimas); transparência sobre o envio à OpenAI (LGPD)                         | —                                                           | os três, com texto curto no painel                                                                                                                                                                             | 10, 05         |
| D5  | Mudanças no prompt: delimitar lição e pergunta, "a lição é dado, não ordem", `max_output_tokens`                                                         | —                                                           | aprovar **e** rodar o `ai-eval/` antes e depois (~US$0,09 por rodada)                                                                                                                                          | 10, ADR-005    |
| D6  | Worker de fila no Railway para áudio e push (custo de um processo a mais)                                                                                | ADR-003                                                     | sim, com a fila `database`, **depois de medir** no Railway quanto tempo a geração de áudio ocupa o processo web (13; Revisão 1)                                                                                | 14             |
| D7  | Confirmação ao publicar lição                                                                                                                            | diálogo / não / só bloquear lição vazia                     | **contestado** (19): atrapalha o fluxo semanal. Bloquear publicação sem conteúdo real é o mínimo; o diálogo fica a critério do PO                                                                              | 15 P2-01       |
| D8  | Campos obrigatórios do cadastro (hoje 7)                                                                                                                 | —                                                           | rever com professores; nome + WhatsApp sozinhos deixam a pessoa sem login (19)                                                                                                                                 | 08, 15 P2-14   |
| D9  | Teto de custo para o áudio (hoje sem limite diário)                                                                                                      | limite por professor/dia                                    | 1 geração por lição por dia                                                                                                                                                                                    | 10, 05 S-14    |
| D10 | Prazo de validade do link pessoal (`EBD_ACCESS_LINK_TTL_DAYS`, hoje vazio = não expira) e limite de 10/min e 50/dia por IP (R2: Wi-Fi da igreja / CGNAT) | —                                                           | TTL de 30 dias; limite por token, não por IP                                                                                                                                                                   | 05 S-03, 19 R2 |

## Ações técnicas imediatas (não dependem de decisão)

1. **Revisar e fazer o merge desta branch** (`engineering/ebd-360`, só local; nada foi enviado). Ela tem as correções L1–L15 e os documentos. A mudança em `compose.yaml` é anterior e não faz parte dela.
2. **Antes do merge:** em produção, só leitura, `select count(*) from users where length(phone) > 15`. Se houver algum, corrigir os números ou subir o teto do telefone para 20 (19, "Continua aberto").
3. **Antes do merge:** rodar `php -i | grep -i -E "max_execution|timers|opcache.enable"` no contêiner de homologação (P1-05 e o opcache, inconclusivos no 19).
4. Escrever os testes de caracterização do 16 (itens 3, 4, 5, 9 e 10) **antes** de qualquer refatoração do "encontro atual" (P2-12).
5. Teste de concorrência da chamada com dois processos (P1-07).
6. Corrigir o acoplamento por cópia do `ai-eval/run-eval.php` (19 R6): expor o `input()` do `AnswerStudyQuestion` antes de usar a avaliação como critério.
7. P2 de baixo risco sem decisão: P2-02, P2-03, P2-05, P2-06, P2-07, P2-08.

## Para validar com pessoas reais (não dá para simular)

- Aluno idoso no próprio celular, com a fonte aumentada (roteiro: 12, persona 2).
- Professor com dificuldade tecnológica preparando e publicando uma lição (12, persona 7).
- O plano pedagógico do 09 com uma classe, durante 4 domingos.

## Ambiente que ficou no laboratório

- Banco `ebd_lab` no contêiner `ebd-postgres-1` (dados sintéticos e o que as personas criaram). Pode ser apagado com `docker exec ebd-postgres-1 dropdb -U ebd ebd_lab` quando não for mais útil.
- O servidor da porta 8011 é desligado no fim da sessão.
- O Playwright e os scripts ficam no scratchpad da sessão, fora do repositório.
