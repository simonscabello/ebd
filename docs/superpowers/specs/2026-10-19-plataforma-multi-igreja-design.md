# Plataforma para várias igrejas — design

**Data:** 2026-10-19 · **Autor da ideia:** Simon (dono do sistema) · **Situação:** aguardando aprovação final

## Intenção e o porquê

Hoje o app atende uma igreja: a do Simon, com a classe de Jovens usando de verdade. A rotina do aluno e do professor está "excelente do jeito que está" (palavras dele). Existe uma demanda pequena e concreta: uma pessoa próxima quer usar na igreja dela, do mesmo jeito.

A ideia é transformar o app numa ferramenta de estudo bíblico para **qualquer igreja ou grupo** que se reúne para estudar a Bíblia. A EBD de domingo é o caso principal, mas também entram grupos que se reúnem na quarta, por exemplo. Mais adiante, o Simon quer vender os **estudos próprios** dele (e, no futuro, os de parceiros) para quem quiser usar. A venda **não** entra agora; entra a estrutura que permite liberar os estudos dele para outras igrejas.

A confissão de fé e a teologia do produto não são preocupação agora: "só fazer a parte que funciona, que resolve alguma coisa".

## Objetivos

1. **Várias igrejas, dados separados.** Cada igreja vê só as próprias classes, pessoas, lições, presenças, anotações e dúvidas.
2. **Cadastro sozinho.** A pessoa conhece o produto num site institucional, se cadastra de graça, cria a igreja e vira a administradora dela. Não depende de aprovação do Simon.
3. **Administrador da igreja.** Cria classes, nomeia professores, convida pessoas e cadastra lições, tudo dentro da igreja dele.
4. **Agenda por classe.** Cada classe define o dia da semana em que se reúne; o padrão de uma classe nova é domingo. Cada igreja define o fuso horário.
5. **Painel do dono (Simon).** Acima de todas as igrejas:
    - vê todas;
    - libera os estudos dele para a igreja que quiser;
    - liga ou desliga a IA (tirar dúvida e gerar áudio) por igreja, com limites.
6. **Estudos do Simon disponíveis para outras igrejas.** Uma série escrita por ele pode ser liberada para uma igreja, que passa a usá-la nas classes dela.
7. **Site institucional** na identidade visual atual: o app funcionando, estudos de exemplo, como usar, benefícios, e o botão de cadastro.
8. **Marca trocável.** O nome ("EBD"), o ícone e as cores ficam num lugar só, para a troca futura de marca ser uma mudança de configuração e de arquivos de identidade, não uma caça a textos no código.

## Não objetivos (fica para depois)

- Venda, pagamento, catálogo com preço ou planos pagos.
- Estudos de parceiros (só a estrutura não pode impedir).
- Telas para uma pessoa que participa de igrejas diferentes. Se acontecer, funciona (ver modelagem), mas sem interface própria.
- Nome e marca novos.
- Texto bíblico para outras igrejas: elas veem só a referência (ex.: "Lc 8.22-25"), sem o texto.

## Anti-objetivos (seria fracasso mesmo funcionando)

- **Mudar a rotina do aluno ou do professor da igreja do Simon.** Login, link pessoal, lição da semana, plano de leitura, anotações, Modo Domingo, chamada, gestão da classe: tudo continua igual para quem já usa. A migração tem de ser invisível: os mesmos links pessoais, sem ninguém precisar entrar de novo, sem perder presença, anotação ou progresso.
- **Vazar dado de uma igreja para outra**, em qualquer tela, busca, relatório, notificação, ferramenta MCP ou resposta da IA.
- **Uma igreja gastar a OpenAI do Simon sem ele liberar.**
- **O texto bíblico licenciado (NAA/SBB) aparecer para outra igreja.**
- **Complicar a vida do professor** com conceitos novos que ele não pediu. A igreja e o administrador ficam numa camada acima; o professor continua vendo a classe dele.

## Restrições

- A stack não muda: Laravel 13 + Inertia 3/React 19 + Postgres no Railway, o mesmo app. O site institucional e o cadastro ficam nele.
- Produção publica a cada merge na `main`. As migrações precisam rodar sem parar o app e sem perder dados, e precisam ter um teste de migração com dados parecidos com os de produção.
- O servidor MCP (`/mcp`) continua funcionando e precisa respeitar a igreja.
- Os testes nunca usam a rede (`Http::preventStrayRequests`, ADR-006).
- As lições atuais da igreja do Simon são **estudos baseados na revista** (não transcrição), com crédito. Ficam só dentro da igreja dele.

## Como funciona hoje (o que o design precisa mexer)

- Não existe "igreja" no banco. `config/ebd.php` tem `church_name` e `timezone` globais. `church_name` aparece no topo do app (`HandleInertiaRequests`) e no e-mail de senha. O fuso é usado por `ChurchCalendar` e pelo agendador (`routes/console.php`).
- `classrooms` (slug global), `series` (pertence a uma classe), `lessons` (pertence a uma classe; slug global; URL pública `/licoes/{slug}`), `class_meetings` (encontros por data), `classroom_user` (papel por classe: professor ou aluno).
- `users.is_admin` é o admin global. Hoje ele é o Simon e faz o papel que agora se divide entre "dono do sistema" e "administrador da igreja".
- A semana gira em torno do domingo: `ChurchCalendar` e `readingWeekday` (segunda a domingo), `FillSundays`, o Modo Domingo, os lembretes (9h e 20h, sábado 8h) e textos "domingo" em cerca de 34 arquivos do front. `ChurchCalendar` é usado em 38 arquivos do back-end.
- O cadastro público (Fortify) cria só um usuário, sem igreja.
- Auditoria e inventário completos: `docs/engineering/ebd-360/` (01, 02 e 03).

## Modelagem decidida

**Igreja (organização).** Entidade nova: nome, slug, fuso horário, data de criação e configurações da IA:

- IA ligada/desligada;
- limite diário de dúvidas por pessoa;
- áudio ligado/desligado;
- limite de áudios.

Toda classe pertence a uma igreja. Série e lição continuam pertencendo à classe, e a igreja vem da classe.

**Pessoas e papéis.**

- Uma pessoa tem **um único login**. A igreja dela é a das classes de que participa. Se um dia ela entrar numa classe de outra igreja, isso funciona sem nada especial (decisão do Simon: "se não for custoso").
- **Administrador da igreja:** vínculo pessoa × igreja (pode haver mais de um). Quem cria a igreja pelo cadastro vira o primeiro.
- **Professor e aluno:** continuam por classe, como hoje.
- **Dono do sistema:** só o Simon (decisão dele). O `is_admin` de hoje passa a significar isso. Na igreja dele, ele também é administrador da igreja. Quem cria uma igreja pelo cadastro é administrador **daquela** igreja e só; nunca vira dono do sistema.

**Agenda por classe.** A classe tem dia da semana e horário de encontro; o padrão é domingo. "Hoje", "esta semana", "próximo encontro", a janela de leitura da semana e os horários dos lembretes passam a ser calculados pelo dia da classe e pelo fuso da igreja. _Proposta:_ a semana de leitura vai do dia seguinte ao encontro até o próximo encontro. Para quem se reúne no domingo, isso é exatamente segunda a domingo, igual a hoje.

**Acervo de estudos (do dono e de cada igreja).** Decidido pelo Simon:

- **Acervo do dono.** Uma área central, só do Simon, onde ele sobe os estudos próprios (séries e lições fora de qualquer classe). A série _Fora de Contexto_, feita por eles e hoje em rascunho na igreja dele, é a primeira a ir para lá. O MCP continua sendo o caminho de preparo do Simon, então as ferramentas de rascunho do MCP também precisam gravar no acervo do dono, além da tela na gestão.
- **Acervo da igreja.** Cada igreja tem o próprio acervo, onde o administrador e os professores dela cadastram estudos da igreja, que depois são usados nas classes. _Interpretação de Claude, a confirmar:_ "seria bom o admin/professor conseguir fazer esse cadastro também".
- **Acesso aos estudos do dono é por pedido, com amostra.** A igreja vê a lista dos estudos do Simon (título, descrição, nº de lições) e pode ler uma **amostra**, geralmente as duas primeiras lições, antes de pedir. O botão "Solicitar acesso" leva o pedido ao Simon, que aprova ou recusa no painel dele. Só depois de aprovado o estudo inteiro fica disponível para usar nas classes.
    - _Decisão de Claude:_ o Simon marca, em cada estudo, quais lições são amostra; o padrão são as duas primeiras.
    - A amostra é só para leitura (não pode ser copiada para uma classe antes da aprovação).
    - As mesmas lições de amostra servem de "estudos de exemplo" no site institucional.
- **O uso é por cópia.** Ao usar um estudo numa classe, a classe recebe uma cópia que a igreja edita à vontade.
    - Presença, anotações e leituras ficam presas à cópia, então nada se perde.
    - Quando o Simon publica uma versão nova do original, a igreja vê "nova versão disponível" e escolhe se aplica. Aplicar atualiza o texto da cópia; o que a igreja tinha mudado é mostrado antes, para ela decidir.
    - Vale também no futuro, com material pago.
- **Fora do escopo agora, mas a modelagem não pode impedir:** gerar uma versão para impressão (revista impressa) de um estudo, para quem não usa tecnologia.

**Texto bíblico.** O texto da NAA aparece só para a igreja do Simon. Configuração por igreja: "mostrar texto bíblico" ligado só onde há licença. Nas outras, aparece a referência, e o "Tirar dúvida" recebe só a referência, não o texto.

**Marca.** Nome do produto, nome curto, ícones, cores principais e textos do site num único arquivo de configuração mais a pasta de identidade. O nome da igreja continua vindo da igreja.

## Telas e fluxos novos

1. **Site institucional** (`/`, para quem não está logado):
    - o que é;
    - capturas do app funcionando;
    - 1 ou 2 estudos de exemplo abertos;
    - "como funciona" em 3 ou 4 passos;
    - benefícios (aluno estuda a semana toda, professor acompanha a turma, chamada no celular);
    - botão "Criar minha igreja".

    Quem está logado continua caindo no app, como hoje.

2. **Cadastro da igreja:** nome da pessoa, e-mail, senha, nome da igreja, fuso (padrão São Paulo). Ao terminar, cai no painel da igreja, com um passo a passo para começar: criar a primeira classe, convidar o professor, cadastrar a primeira lição.
3. **Painel do administrador da igreja:** classes (criar, editar, dia e horário), pessoas (convidar professor e aluno pelos mecanismos que já existem: link pessoal, e-mail), lições e séries da igreja, e estudos liberados pelo dono.
4. **Painel do dono:**
    - lista de igrejas (nome, criação, nº de classes e pessoas, último uso);
    - liberar estudos;
    - ligar a IA e definir limites por igreja;
    - ver o uso de IA por igreja (perguntas e áudios no mês).
5. **Professor e aluno:** sem telas novas. Mudam só os textos fixos "domingo", que viram o dia da classe, e o Modo Domingo, que pode virar "Modo Encontro" para classes que não se reúnem no domingo. Na igreja do Simon, tudo continua dizendo "domingo".

## Migração da igreja atual

- Criar a igreja do Simon com o nome e o fuso atuais da configuração.
- Colocar todas as classes nela, com o dia "domingo".
- O Simon vira administrador dela e continua dono do sistema.
- Ligar a IA, o áudio e o texto bíblico nela, com os limites de hoje (`EBD_HELPER_DAILY_LIMIT`).

Ninguém perde sessão, link pessoal ou dado. A migração precisa de um teste que monte um banco parecido com o de produção e confira, depois da migração, que nada mudou para aluno e professor.

## Fronteiras entre igrejas (decisões de Claude, para o Simon conferir)

- **Lição pública continua pública por link.** Qualquer pessoa com o link abre, como hoje (o visitante que recebe pelo WhatsApp). Isso preserva a rotina atual.
- **Listagens são da igreja.** A home e a `/biblioteca` de quem está logado mostram só as lições da igreja dele. O visitante anônimo não recebe uma listagem de todas as igrejas: a `/` vira o site institucional, e a biblioteca anônima mostra só os estudos de exemplo do site.
- **O texto bíblico segue a igreja da lição, não a de quem lê.** Uma lição da igreja do Simon mostra o texto da NAA para qualquer leitor, inclusive o visitante anônimo pelo WhatsApp, como hoje. Uma lição de outra igreja mostra só a referência. A rota `/biblia?ref=…` passa a exigir o contexto da lição (de qual lição veio o pedido) e decide por ele; o cache dela leva isso em conta.
- **Estudos do dono liberados para outras igrejas** seguem a mesma regra: na igreja que recebe, só a referência. Versículos da NAA colados no meio do texto do estudo o sistema não consegue filtrar. Regra de conteúdo: ao escrever estudos para liberar, citar a referência e não colar o texto da NAA.

## Cadastro e contas (decisões de Claude, para o Simon conferir)

- O cadastro aberto "só de usuário" deixa de existir. O cadastro público é o "Criar minha igreja". Alunos e professores entram por convite ou link pessoal, como hoje.
- Quem já tem conta (por exemplo, um aluno da igreja do Simon) pode criar uma igreja nova com o mesmo login, na tela "Criar minha igreja", estando logado.
- Uma pessoa logada sem nenhuma classe e sem igreja administrada vê uma tela simples: "Peça o link ao seu professor" ou "Crie sua igreja".
- `is_admin` hoje: só **1** conta em produção (conferido em 2026-10-19, só leitura). Vira "dono do sistema" sem risco de dar acesso a todas as igrejas a mais ninguém.

## O que fica para quem for construir

- A forma de garantir o isolamento entre igrejas (escopo global no Eloquent, filtro explícito nas Queries ou os dois) — escolha do builder, **com uma matriz de testes de autorização entre duas igrejas** cobrindo telas, busca da biblioteca, relatórios, lembretes, aba Dúvidas, MCP e IA.
- Como o agendador dispara lembretes em fusos diferentes.
- Slugs: continuam globais (com sufixo em colisão), para não mudar URLs.
- A ordem das entregas. Sugestão: (1) igreja + migração invisível; (2) agenda por classe; (3) administrador da igreja e cadastro; (4) acervos (dono e igreja), pedido de acesso, cópia e nova versão; painel do dono e IA por igreja; (5) site institucional; (6) marca centralizada.
- Os detalhes visuais do site e dos painéis, seguindo a identidade atual (estilo moderno e chapado, sem brilho nem ilustração).

## Decisões já tomadas pelo Simon (2026-10-19)

- Estudo do dono usado por cópia, editável, com aviso de nova versão.
- Acervo central do dono, mais acervo por igreja.
- Acesso aos estudos do dono por pedido e aprovação, com amostra (em geral as duas primeiras lições) antes do pedido.
- Dono do sistema: só o Simon.
- IA nas outras igrejas só quando o Simon liberar.
- Nome "EBD" por enquanto, com a marca trocável.
- Na igreja dele, nada muda para aluno e professor.
