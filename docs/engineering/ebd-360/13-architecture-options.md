# 13 · Opções de evolução

Ponto de partida (ver 01–03): um monólito Laravel + Inertia de tamanho modesto (225 classes PHP, 271 arquivos TS/TSX, 373 testes no ponto de partida). Atende uma igreja, com 1 serviço web e 1 serviço `scheduler` no Railway, fila `sync` e sem worker. A autorização entre classes está correta e foi verificada por três caminhos independentes (05, 04 e 12). Os defeitos encontrados são localizados, não estruturais.

## Os três cenários

### A · Consolidação

Corrigir o que foi achado, simplificar o que duplica e fechar as lacunas de teste, sem funcionalidade nova.

- Itens: o backlog P0–P2 (15), uma única definição de "encontro atual" (hoje são 4; 01 #3), tirar a escrita do GET em Domingos (01 #2), worker de fila para áudio e push (01 #1), documentação em dia.
- Custo: baixo a médio, em partes pequenas e reversíveis.

### B · Evolução educacional

A, mais o que melhora o estudo em si: as anotações e as dúvidas voltam no domingo e na revisão, perguntas de reflexão escritas pelo professor, revisão da semana e apoio ao professor para preparar a aula (09, 10).

- Depende de A: uma "lição atual" coerente e autosave confiável (L11) são pré-requisitos.
- Custo: médio. O risco está mais na adoção e na pedagogia do que na técnica, e precisa de validação com alunos e professores (09, plano de validação).

### C · Expansão controlada

Preparar para outras igrejas (multi-tenancy, onboarding de organizações, cobrança).

- Hoje não há nenhum pedido nem evidência de demanda. O nome da igreja, o fuso e a Bíblia são configuração global (`config/ebd.php`). Classes, séries e lições não têm `organization_id`.
- Custo: alto e espalhado (toda query, policy, ferramenta MCP, a busca e os lembretes). Também aumenta a superfície de privacidade: dados de presença e anotações de igrejas diferentes no mesmo banco.

## Comparação

| Critério                | A Consolidação                     | B Evolução educacional         | C Expansão                      |
| ----------------------- | ---------------------------------- | ------------------------------ | ------------------------------- |
| Valor pedagógico        | indireto (confiabilidade)          | **alto**, se validado          | nenhum para a igreja atual      |
| Complexidade adicionada | baixa (às vezes negativa)          | média                          | alta                            |
| Custo operacional       | ≈ igual (um worker a mais)         | + uso de IA, se usar IA        | + suporte, cobrança, isolamento |
| Manutenção              | melhora                            | estável                        | piora                           |
| Adoção                  | melhora (menos falhas silenciosas) | depende de validação           | —                               |
| Risco                   | baixo                              | médio (pedagógico)             | alto                            |
| Privacidade             | melhora (S-01, S-07, IA)           | atenção às anotações e dúvidas | piora                           |
| Escalabilidade          | suficiente para uma igreja         | suficiente                     | necessária só se houver demanda |

## Recomendação

**A agora, B em seguida e em fatias validadas. C não**, até existir uma segunda igreja de verdade pedindo.

- A resolve problemas observados: falha silenciosa offline, P1 de privacidade (S-01), lembretes que param e erros 500.
- B é onde está o propósito do produto ("incentivar o estudo durante a semana"). Mas o próprio 09 mostra que hoje mede-se _atividade_ (marcar leitura), não _aprendizado_. Antes de construir, vale validar com uma classe.
- C: as decisões que facilitariam um futuro multi-igreja sem custo hoje são só não espalhar mais configuração global em código e manter tudo pendurado em `Classroom`. Ver ADR-004.

## Revisão adversarial desta proposta

Ver 19, "Revisão 3". Pontos já levados em conta aqui:

- "Worker de fila" pode parecer complexidade. O argumento a favor é concreto: o áudio roda no processo web com `set_time_limit(0)` (01 #1), e uma queda deixa a lição "gerando" por até 15 min. Mesmo assim fica condicionado a medir, no Railway, quanto tempo a geração ocupa.
- "Unificar o encontro atual" é refatoração; só deve ser feita depois de testes de caracterização das quatro versões (16).
