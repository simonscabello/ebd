# 18 · Resultados da validação

Ambiente: Linux (WSL2, Ubuntu 26.04), PHP 8.5.4 CLI, Node 24.22. Postgres do compose (`ebd-postgres-1`) na porta 5432, banco `ebd_testing` (o mesmo de `make test`). Os testes no navegador usaram um banco à parte, `ebd_lab`, criado para esta missão com `migrate --seed` (só dados sintéticos). Os bancos `ebd` (desenvolvimento) e `postgres` não foram tocados, nem o contêiner `orc-pg-lab` de outro projeto. Nenhum `migrate:fresh` ou reset de volume.

## Baseline (antes de qualquer mudança, `e03fd30`)

| Verificação       | Comando                                                                       | Resultado                                                                                                                           |
| ----------------- | ----------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------- |
| Build             | `npm run build`                                                               | ok                                                                                                                                  |
| PHPUnit           | `php artisan test` (com `OPENAI_API_KEY=` na linha de comando, por segurança) | **373 testes: 372 passaram, 1 pulado**, 2.787 asserções, 67 s                                                                       |
| Pint              | `vendor/bin/pint --test`                                                      | ok                                                                                                                                  |
| Larastan          | `vendor/bin/phpstan analyse`                                                  | 0 erros                                                                                                                             |
| TypeScript        | `npx tsc --noEmit`                                                            | ok                                                                                                                                  |
| Lint/format front | `npx vp check`                                                                | falhou **só** em 3 arquivos `.omc/` (estado de uma ferramenta local, fora do projeto e não versionado). O código do projeto passou. |

## Final (com os lotes 1 a 4; suíte rodada de novo depois do último)

| Verificação                                                                          | Resultado                                                                                                                            |
| ------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------ |
| PHPUnit (sem sobrescrever a chave na linha de comando: o `phpunit.xml` agora a zera) | **380 testes: 379 passaram, 1 pulado**, 2.809 asserções, 43 s                                                                        |
| Pint, Larastan, `tsc`, `vp check app resources tests`, build                         | todos ok                                                                                                                             |
| Migration nova                                                                       | `migrate` → `rollback` → `migrate` no `ebd_lab`, duas vezes (antes e depois do ajuste do Codex); 4 índices presentes em `pg_indexes` |
| Navegador (Playwright, 360×740)                                                      | cenário offline da anotação: antes falhava, depois passa (ver 17, L11)                                                               |

O teste pulado é o mesmo do baseline (`skipUnlessFortifyHas`, recurso do Fortify desligado).

## Prova de que os testes novos pegam os bugs

- Revertendo as correções no working tree: os testes de L2, L3 (com 23 dígitos dá `value too long`, o 500) e L4 falham.
- A Revisão 4 rodou os testes da branch contra um `git archive main`: L2, L4 e L5 falham na `main`, e a chave mal formada lança exceção (L1).

## Cenários pedidos na missão

Legenda: ✅ coberto por teste automatizado (existente ou novo) · 🌐 observado no navegador · ⚠️ coberto em parte · ❌ sem teste.

| Cenário                                 | Situação                                                                                                                                                                            | Onde                      |
| --------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------- |
| Domingo sem EBD                         | ✅                                                                                                                                                                                  | testes de agenda (ver 16) |
| Encontro cancelado e restaurado         | ⚠️ existe teste; "restaurar não desfaz o empurrão" não é testado                                                                                                                    | 16, lacuna 10             |
| Aula que continua na semana seguinte    | ✅ `AttendanceAndInsightsTest::test_finish_meeting_with_continue_moves_lesson_to_next_sunday`; a leitura da 2ª semana tem defeito conhecido (04 B1) **sem** teste de caracterização | 15 P1-04                  |
| Conclusão e reabertura de encontros     | ⚠️ encerrar ✅ (+ novo teste do "continua" sem lição); reabrir não foi testado no navegador (só aparece no domingo)                                                                 | 12                        |
| Troca de classe                         | ✅ `StudentPagesTest::test_moving_a_student_needs_both_classrooms`                                                                                                                  |                           |
| Professor de uma classe acessando outra | ✅ testes de policy + 🌐 ~70 tentativas sem nenhuma falha                                                                                                                           | 12, 05                    |
| Link pessoal revogado                   | ✅ + 🌐                                                                                                                                                                             | 12                        |
| Material restrito                       | ✅                                                                                                                                                                                  | 16                        |
| Leitura marcada duas vezes              | ✅ unique no banco + 🌐 duplo toque não duplica                                                                                                                                     | 06, 12                    |
| Mudança de data e fuso                  | ✅ 16 arquivos usam `travelTo`, vários em `Europe/Madrid`                                                                                                                           | 16                        |
| Frequência duplicada                    | ✅ unique `(class_meeting_id, user_id)` + `insertOrIgnore`                                                                                                                          | 06                        |
| Salvar chamada simultaneamente          | ⚠️ corrigido com `lockForUpdate` (L9), **sem** teste com dois processos                                                                                                             | 15 P1-07                  |
| Progresso de lição anterior             | ⚠️ coberto em parte (16)                                                                                                                                                            |                           |
| Usuário sem cadastro completo           | ✅ + 🌐                                                                                                                                                                             |                           |
| Inscrição push inválida                 | ✅ **novo**: `WebPushSenderTest` (2 testes)                                                                                                                                         | L1                        |
| Falha em serviço de IA                  | ✅ existente (400) + **novos**: nova tentativa limitada (503), lock em paralelo; 🌐 sem chave o botão some e o POST dá 422 claro                                                    | L5, 12                    |

## Não executado (e por quê)

- **IA de verdade e o conjunto de 60 casos** (`ai-eval/`): exige chamadas pagas à OpenAI, que não foram autorizadas. O harness `run-eval.php` está pronto e recusa rodar sem `--execute` e `EBD_EVAL_CONFIRM=sim`. Custo estimado de uma rodada: ~US$0,09 (ver 10).
- **Concorrência real** (duas chamadas ao mesmo tempo, duas perguntas em paralelo com Redis): só verificado por leitura de código e pela revisão do Codex.
- **Container de produção** (FrankenPHP): a hipótese de `max_execution_time = 30` contra o timeout de 60 s da IA (10 AI-02) precisa de `php -i` dentro da imagem. Não rodei Docker build.
- **Telas de erro de produção:** o laboratório roda com `APP_DEBUG=true`.
- **Encerrar e reabrir aula no navegador:** os botões só aparecem no domingo, e não alterei o relógio do servidor.
- **Leitor de tela real** (TalkBack/VoiceOver): as correções de a11y foram conferidas no código, não com tecnologia assistiva.
- **Medição do ganho do `BibleBook`** depois da mudança: o número (0,61 → 0,0012 ms) é do protótipo do agente de performance, em PHP CLI sem opcache.
