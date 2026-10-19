# 14 · Decisões de arquitetura (ADRs)

Status possíveis: **Proposta** (aguarda o PO), **Aceita** (já é como o código funciona ou foi feita nesta missão com base em defeito comprovado).

---

## ADR-001 · Manter o monólito Laravel + Inertia com Actions e Queries

**Status:** Aceita (registra o que existe).

**Contexto.** O código já separa Actions (escrita), Queries (leitura) e Policies, com controllers finos na maior parte (01). O tamanho é modesto e há uma só equipe.

**Decisão.** Não introduzir camadas novas (repositories, DTOs genéricos, módulos/pacotes) sem um problema demonstrado. O que se corrige é a duplicação pontual, como as quatro definições de "encontro atual".

**Consequências.** Menos cerimônia. O risco de acoplamento continua sendo controlado pelos testes.

---

## ADR-002 · Falhas de rede no front são estados visíveis, nunca silenciosos

**Status:** Aceita (implementada em L11/L12).

**Contexto.** No Inertia 3, `onError` é só para validação. Falha de rede ou HTTP chega por `onNetworkError` e `onHttpException`. Sem eles, a anotação e a chamada ficavam em "Salvando…" e o texto se perdia (12).

**Decisão.** Toda gravação automática trata os três callbacks, oferece "Tentar de novo", tenta de novo no evento `online` quando a operação é idempotente e avisa antes de sair com dados não salvos.

**Consequências.** Fica pendente aplicar o mesmo às ações pontuais que falham caladas offline: marcar leitura e navegação (15 P2-02).

**Limitação conhecida (Revisão 4).** Navegar dentro do app desmonta o componente e cancela o salvamento pendente: o texto digitado no último 1,2 s se perde sem aviso, porque o `beforeunload` só cobre fechar ou recarregar a página. Ver 15 P2-19.

---

## ADR-003 · Trabalho demorado sai do processo web

**Status:** Proposta.

**Contexto.** A fila é `sync` e não há worker. Áudio (até minutos, `set_time_limit(0)`), push e IA ocupam threads do servidor web. Uma queda no meio deixa o áudio "gerando" (01 #1; 06; 11).

**Decisão proposta.** Um worker `queue:work` com fila `database` (sem serviço novo: usa o Postgres que já existe). Começar só pelo áudio e pelo push de publicação. A IA continua síncrona, porque o aluno espera a resposta na tela.

**Alternativas.** Redis (já previsto no compose, mas seria mais um serviço pago no Railway); deixar como está (aceitável enquanto o uso de áudio for raro).

**Consequências.** Mais um processo no Railway (custo baixo). Exige monitorar jobs com falha. **Precisa de aprovação** (custo e operação).

---

## ADR-004 · Não implementar multi-tenancy por antecipação

**Status:** Proposta (recomendada).

**Contexto.** Não há demanda de outra igreja (13). O custo seria alto e espalhado, e aumentaria o risco de privacidade.

**Decisão.** Não criar `organization_id`. Manter "tudo pendurado em `Classroom`" e configuração de igreja em `config/ebd.php`. Reavaliar quando houver um pedido concreto.

---

## ADR-005 · O "Tirar dúvida" é apoio ao estudo, com limites técnicos explícitos

**Status:** Aceita em parte (L5); o resto é Proposta.

**Contexto (10).** Uma pergunta por vez, sem histórico, com a lição como contexto e sem o conteúdo do professor. Limite diário. Falhas sem diagnóstico, tentativas demais, corrida no limite.

**Decisão aceita.** No máximo 2 tentativas, lock por pessoa, log de status e código do erro (sem o conteúdo).

**Decisões propostas (precisam do PO).**

- `store=false` na API da OpenAI (privacidade).
- Delimitar a lição e a pergunta (`<licao>`, `<duvida_do_aluno>`) e uma frase dizendo que o texto da lição é dado, não instrução (muda o prompt).
- `max_output_tokens`.
- Avisar o aluno de que o professor vê as dúvidas, de forma anônima.
- Rodar o conjunto de avaliação (`ai-eval/`, ~US$0,09) antes de qualquer mudança no prompt e depois dela.

A IA não cria doutrina nem substitui o professor; o prompt já pede que a dúvida seja levada à aula.

---

## ADR-006 · Testes nunca falam com a rede

**Status:** Aceita (L10).

**Decisão.** `phpunit.xml` zera `OPENAI_API_KEY` com `force="true"`. `TestCase` chama `Http::preventStrayRequests()`. Clientes que não usam o `Http` do Laravel (web-push) recebem um cliente PSR-18 injetável.

---

## ADR-007 · Recuperação de acesso pelo link pessoal

**Status:** Proposta (decisão do PO; liga-se a S-01).

**Contexto.** O link de recuperação apaga a senha e entra como o aluno por 400 dias. É uma regra intencional, porque o e-mail de "esqueci minha senha" não é usado. Combinada com "adicionar por e-mail" (qualquer conta existente vira aluno da classe de quem a adicionou), permite que um professor entre na conta de um aluno de outra classe (05 S-01).

**Opções.**

1. "Adicionar por e-mail" só para quem ainda não é aluno de nenhuma classe; o resto passa pela administração.
2. Link de recuperação só para quem é aluno _apenas_ de classes que o emissor gerencia.
3. Exigir confirmação do aluno, ou da administração, para o vínculo.
4. Aceitar o risco, já que os professores são pessoas de confiança, e só registrar em AuditLog (S-04).

**Recomendação técnica (revista depois da Revisão 1).** Opção 1 + AuditLog: bloquear o "adicionar por e-mail" de quem já é aluno de uma classe que o professor não gerencia, o que fecha a origem do problema (inclusive R4: bloquear ou revogar aluno de outra classe). O vínculo continua possível pela administração, e o link de recuperação fica como está para os alunos da própria classe.
