# Didaché — Fase 0: diagnóstico

**Data:** 2026-10-19 · **Base:** `main` @ `3df20f6` · **Fontes:** auditoria completa em `docs/engineering/ebd-360/` (01–21), design aprovado em `docs/superpowers/specs/2026-10-19-plataforma-multi-igreja-design.md`, entrega 1 em produção (PR simonscabello/ebd#35) e a referência visual aprovada em `docs/brand/didache-referencia-aprovada.webp`.

Este documento compara a missão "Transformação do projeto EBD em Didaché" com o código real e com o que já foi decidido. Ele separa o que já existe, o que precisa ser adaptado e o que é novo, e aponta onde a missão nova muda decisões anteriores.

## 1. Stack e estado real (verificado no código)

- **Stack:** Laravel 13 (PHP 8.4), Inertia 3 + React 19 + TypeScript, Tailwind 4, PostgreSQL. Fortify (login), Passport + `laravel/mcp` (servidor MCP em `/mcp`), Web Push, PWA (`public/manifest.webmanifest`, `public/sw.js`) e OpenAI (dúvidas e áudio).
- **Deploy:** Railway, com os serviços `app` (FrankenPHP, Dockerfile) e `scheduler`, e um ambiente `homolog`. O merge na `main` publica em produção.
- **Testes:** 392 (PHPUnit), mais Larastan, Pint, tsc e `vp check`.
- **"Transformação SaaS em andamento":** só a **entrega 1**. Já existem a tabela `churches` (a organização), `church_user` (administradores da organização) e `classrooms.church_id`. A igreja atual foi migrada para dentro dela. Ninguém ainda cria organizações, e o isolamento entre organizações **não** está implementado nas consultas, porque só existe uma organização.
- **Plano da entrega 2** (agenda por classe), escrito e não executado: `docs/superpowers/plans/2026-10-19-entrega-2-agenda-por-classe.md`.

## 2. Missão × código

Legenda: ✅ existe · 🟡 existe, precisa adaptar · 🆕 novo · ⛔ fora de escopo pela missão.

| Item da missão                                                      | Situação        | Onde está / o que falta                                                                                                                                                                                                   |
| ------------------------------------------------------------------- | --------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Organizações com isolamento                                         | 🟡              | Entidade `churches` existe (entrega 1). Faltam o cadastro, o administrador da organização e o escopo de **todas** as consultas, MCP, busca, relatórios e lembretes por organização, com a matriz de testes de isolamento. |
| Pessoa em várias organizações com papéis diferentes                 | 🟡              | Um login, e o papel por turma via `classroom_user` (professor ou aluno). O admin de organização é `church_user`. Na prática, funciona entre organizações sem nada novo. Falta a tela de troca de organização.             |
| Programas de ensino (EBD, discipulado, pequenos grupos…)            | 🆕              | Não existe. Hoje é organização → turma.                                                                                                                                                                                   |
| Turmas com calendário, pautas, presença e histórico                 | ✅ / 🟡         | Turma = `classrooms`; encontros = `class_meetings`; chamada, encerramento, histórico e relatórios existem. A **agenda por dia da turma** está no plano da entrega 2.                                                      |
| Papéis: admin da plataforma, admin da organização, professor, aluno | ✅ / 🟡         | `users.is_admin` (só o Simon), `church_user`, `classroom_user.role`. Autorização no back-end por Policies (verificada na auditoria 05).                                                                                   |
| Papéis: coordenador de programa, autor editorial                    | 🆕              | Não existem. Dependem de "programa" e da "biblioteca oficial".                                                                                                                                                            |
| Séries e lições                                                     | ✅ / 🟡         | `series` e `lessons` pertencem à turma. Faltam: autor, capa, público, objetivos da série, histórico de versões e estado de publicação **da série**. A lição já tem status.                                                |
| Estudos oficiais do fundador, gratuitos                             | 🆕              | Não existe acervo fora de uma turma. A série _Fora de Contexto_ está em rascunho dentro da igreja do Simon.                                                                                                               |
| Cópia personalizável ("fork") com versão de origem                  | 🆕              | —                                                                                                                                                                                                                         |
| Aviso de nova versão e comparação básica                            | 🆕              | —                                                                                                                                                                                                                         |
| "Apoie a Didaché" (Pix, sem cobrança)                               | 🆕              | Precisa da chave Pix ou do QR do Simon (não invento valores nem botões falsos).                                                                                                                                           |
| Limites de upload, rate limit, paginação, custos                    | 🟡              | Upload por tipo (`MaterialType::maxUploadKilobytes`), `throttle` no link pessoal, na IA e no push. As lacunas estão listadas em `docs/engineering/ebd-360/05` e `11`.                                                     |
| Marca Didaché (nome, logo, cores, Lora + Inter)                     | 🟡              | Hoje: nome "EBD" (`APP_NAME`, 49 arquivos com "EBD" no front e no back), Inter + **Literata** (não Lora), ícone de marcador com cruz (`public/icons/`), tokens em `resources/css/app.css`.                                |
| Site institucional                                                  | 🆕              | `/` é o início do aluno. Estava previsto na entrega 5.                                                                                                                                                                    |
| Blog                                                                | ⛔ por enquanto | Só uma avaliação documentada, como a missão pede.                                                                                                                                                                         |

## 3. Onde a missão muda decisões já aprovadas

O design aprovado ontem e a missão Didaché divergem em quatro pontos. A missão é mais recente, mas cada um precisa da palavra do Simon antes de ser construído.

1. **Acesso aos estudos oficiais.**
    - Aprovado: a organização lê uma **amostra** (as duas primeiras lições), **pede acesso** e o Simon aprova.
    - Missão: os estudos oficiais são "disponibilizados gratuitamente às organizações", sem pedido.
2. **Atualização da cópia.**
    - Aprovado: a organização vê "nova versão" e pode **aplicar**, substituindo o texto da cópia.
    - Missão: só aviso e **comparação**; "nenhuma alteração é aplicada automaticamente", e mesclar fica fora do escopo.
3. **Programas.** Não existiam no design. A missão pede programas (EBD, discipulado…) com turmas, calendário e estudos.
4. **Venda futura.** O design previa venda de estudos "depois". A missão troca isso por **gratuidade com contribuições voluntárias**, sem marketplace. Isso simplifica o modelo: a amostra e o pedido de acesso deixam de ter motivo, a menos que o Simon queira controlar quem usa.

## 4. Ordem de execução proposta

Ordem pensada para minimizar retrabalho. Cada etapa vai para produção sozinha, com testes.

| #   | Etapa                                                                                                                                                                                                                                    | Por que nesta ordem                                                                                 |
| --- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------- |
| A   | **Marca Didaché:** tokens de cor (escala semântica, contraste validado), Lora nos títulos e Inter na interface, símbolo e logotipo em SVG, favicon, ícones do PWA, imagem Open Graph e o nome "Didaché" no app. Sem mudar comportamento. | Baixo risco, isolada, visível, e prepara todas as telas novas para já nascerem na identidade certa. |
| B   | **Agenda por turma** (o plano da entrega 2, já escrito).                                                                                                                                                                                 | Tira a dependência de domingo antes de existirem outras organizações.                               |
| C   | **Programas + organização no cadastro + isolamento** (antiga entrega 3, ampliada com "programa").                                                                                                                                        | É o coração multi-organização; precisa da decisão 3.                                                |
| D   | **Biblioteca oficial + cópia com versão de origem + aviso e comparação** (antiga entrega 4, reescrita pela missão).                                                                                                                      | Depende de C (organizações) e das decisões 1 e 2.                                                   |
| E   | **Site institucional + "Apoie a Didaché" (Pix)**.                                                                                                                                                                                        | Depende da marca (A) e de D, para mostrar estudos reais. Precisa da chave Pix.                      |
| F   | **Custos e abuso** (limites que faltam, paginação, observabilidade).                                                                                                                                                                     | Pode ir em paralelo depois de C; os itens já estão mapeados na auditoria.                           |
| G   | **Documentação** (README, arquitetura, guia de marca) a cada etapa, e o blog só como proposta.                                                                                                                                           | Contínua.                                                                                           |

## 5. Identidade visual: primeiros resultados (etapa A)

- **Paleta medida na imagem aprovada** (amostragem de pixels):
    - verde oliva `#415646` (a missão indica `#405A49`);
    - terra `#8F6C48` (`#98764F`);
    - areia `#D7C9B0`;
    - papel `#F0EBE1`, com o fundo dos cartões em `#F7F1EA`;
    - carvão `#2C2D2A`;
    - verde profundo dos painéis escuros `#152822`.
      Os tokens finais saem destas medidas, com contraste conferido.
- **Símbolo:** reconstruído à mão em SVG a partir da imagem, sem vetorização automática. São cinco formas: duas páginas, a borda da página e duas folhas com nervura. Arquivo: `docs/brand/rascunhos/simbolo-v3.svg` (comparação em `docs/brand/rascunhos/simbolo-v3-comparacao.png`), comparado lado a lado com a referência.
    - Em 64 e 40 px fica reconhecível.
    - Em 24 e 16 px a nervura some: o favicon precisa de uma versão simplificada (sem nervura, traços mais grossos), derivada do mesmo desenho.
- **Codex:** não é usado para o logotipo. Imagem gerada vem rasterizada e o histórico do projeto mostra que ela fica fora do estilo aprovado. O Codex fica reservado para fotos e ilustrações institucionais (Open Graph, fundos do site), sempre conferidas.

## 6. Riscos

- **Trocar "EBD" por "Didaché" muda o nome e o ícone do app instalado no celular dos alunos atuais.** Em alguns aparelhos o atalho só se atualiza ao reinstalar. Isso precisa ser avisado na igreja.
- **Isolamento entre organizações** é o maior risco técnico (consultas espalhadas, MCP, busca, lembretes). Exige a matriz de testes antes de liberar o cadastro público.
- **Texto bíblico NAA:** continua só na organização do Simon (decisão anterior). A biblioteca oficial cita a referência, não o texto.
- **Domínio:** `ebd.up.railway.app` não combina com a marca. Trocar exige um domínio (custo) e cuidado com os links pessoais já enviados, que precisam continuar valendo.

## 7. Decisões do Simon (2026-10-19)

1. **Acesso aos estudos oficiais:** livre. Qualquer organização vê a biblioteca oficial e copia para si na hora, sem pedido nem amostra.
2. **Nova versão:** só aviso e comparação. Ajustar a cópia é manual, e nada é aplicado automaticamente.
3. **Programas:** entidade leve, organização → programa → turmas. A igreja do Simon ganha o programa "Escola Bíblica Dominical" com as turmas atuais. O coordenador de programa vem junto.
4. **Marca:** o app inteiro vira Didaché já na etapa A, inclusive na igreja do Simon. O endereço `ebd.up.railway.app` continua até haver domínio.
