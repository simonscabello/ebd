# Plano: gestão centrada na lição (Didaché / repo /home/scabello/projetos/ebd)

## Objetivo do produto
- A classe tem uma LIÇÃO ATUAL. "Domingo" é só quando ela acontece. Uma lição pode durar 1, 2 ou 3 domingos; só sai quando o professor clica "Concluir e passar para a próxima".
- As próximas lições vêm da sequência da série (lessons.number), sem datas, sem "domingo sem lição".
- A classe tem uma SÉRIE ATUAL (aparece primeiro em listas/filtros; aberta no acordeão; demais recolhidas). Série pode ser marcada como CONCLUÍDA (vai para o fim, apagada).
- Lições estudadas só aparecem dentro da série delas.
- Telas no padrão Metronic: página da classe = cartão de cabeçalho + abas (Visão geral, Lições, Alunos, Ajustes). Lista de alunos simples; clicar abre a página do aluno com tudo.

## Ideia central da implementação (para não quebrar o que depende de encontros)
Hoje CurrentLessonQuery, StudyWeekQuery/ReadingWeeks, lembretes (SendLessonReminder precisa de encontro amanhã, SendReadingReminders), modo domingo (MeetingForLessonQuery), chamada (RecordAttendance precisa de encontro), badges, WeeklyMessage e relatório dependem de linhas em class_meetings.

Proposta: NÃO remover class_meetings. Mudar quem manda:
- Fonte da verdade nova: `classrooms.current_lesson_id`.
- Os encontros viram projeção: existe sempre só o PRÓXIMO dia de encontro (hoje incluído) como `planned`, ligado à lição atual. Nada de 12 semanas à frente, nada de planejar série.
- Invariante: todo encontro ativo com held_on >= hoje, sem chamada (attendance_taken_at null) e sem finished_at tem lesson_id = current_lesson_id.
- Encontros passados guardam a lição que foi dada naquele dia (histórico).
- Assim CurrentLessonQuery, semana de leitura, lembretes, modo domingo e chamada continuam funcionando quase sem mudança. "Lição continua no próximo domingo" acontece sozinho: se não concluir, o próximo encontro recebe a mesma lição.

## Modelo de dados (1 migração)
- classrooms.current_lesson_id  FK lessons nullable, nullOnDelete
- classrooms.current_series_id  FK series nullable, nullOnDelete
- series.concluded_at  timestamp nullable
- lessons.studied_at   timestamp nullable  (marcada no "Concluir"; "Estudada" na lista)
- Regra: se current_lesson_id tem série, current_series_id = essa série. current_series_id pode existir sem lição atual (série nova sem lição pronta).
- Ordem dentro da série: number (nulls last), depois id.

Backfill (na mesma migração, por classe que holdsMeetings):
1. current_lesson_id = lição que CurrentLessonQuery daria hoje (próximo encontro ativo com lição; senão último realizado).
2. current_series_id = série dessa lição; senão série mais recente por starts_on.
3. lessons.studied_at = último held_on para lições com encontro realizado no passado e que não são a atual.
4. series.concluded_at = ends_on para séries com ends_on < hoje e todas as lições estudadas (ou deixar null e o professor marca).
5. Apagar encontros futuros `planned` sem chamada e sem notas DEPOIS do próximo dia de encontro (as lições deles voltam a ser só "próximas" pela ordem da série). Rodar SyncLessonSchedule nas lições afetadas.

## Backend (ações novas, em app/Actions/Lessons ou app/Actions/Classrooms)
- SyncUpcomingMeeting(classroom): garante o encontro do próximo dia de encontro (ChurchCalendar::nextMeetingDay, hoje incluído) e religa os encontros ativos futuros sem chamada à lição atual (invariante). Chamado por: as ações abaixo, FillSundays (renomear/limitar a 1 dia à frente; agendador diário continua), carregamento da Visão geral.
- SetCurrentLesson(classroom, lesson): define atual (+ série atual), limpa studied_at da escolhida se estava estudada, SyncUpcomingMeeting. ("Estudar esta agora").
- AdvanceLesson(classroom): marca studied_at na atual; próxima = primeira lição não estudada da série atual com number maior (rascunho também vale; preparing já existe para aluno); se não houver, current_lesson_id = null e sugere concluir a série; SyncUpcomingMeeting.
- SetCurrentSeries(classroom, series): current_series_id; current_lesson = primeira não estudada da série (ou null).
- ConcludeSeries(series) / ReopenSeries: concluded_at; se for a série atual, limpa série/lição atual.
- FinishMeeting com `continues=false` passa a chamar AdvanceLesson (pergunta ao final da chamada/aula: "A lição terminou hoje? Sim, passar para a próxima / Não, continua no próximo domingo"). ContinueLessonNextMeeting e ShiftPlannedLessons deixam de ser usados (remover).
- "Fazer chamada": rota GET classes/{c}/chamada → encontro do dia de encontro mais recente <= hoje (firstOrCreate com a lição atual se não existir) → redireciona para meetings/show.
- Rotas: POST classes/{c}/licao-atual, POST classes/{c}/licao-atual/concluir, POST classes/{c}/serie-atual, POST series/{s}/concluir|reabrir. Policies: as mesmas de update da classe/série.
- PlanMeetings, FillSundays 12 semanas, CreateLesson::scheduleOn (meeting_on) saem da interface; meeting_on deixa de existir no formulário de lição.
- "1 de 2" (meetingIndex/meetingTotal): total deixa de ser conhecido. Trocar por "2º domingo nesta lição" (só quando >1) em home.tsx, lessons/show, lessons/sunday, gestão.
- MCP: remover plan_meetings; tirar `continues` de finish_meeting e `shift_lessons` de cancel_meeting; record_attendance cria o encontro do dia se faltar; novas ferramentas set_current_lesson e advance_lesson; get_classroom_overview devolve current_lesson/current_series; save_lesson_draft sem meeting_on. Atualizar skills preparar-licao/revisar-licao e README.

## Telas (React/Inertia)
- ClassroomShell (componente): breadcrumb (Igreja › Classes › Jovens), cartão com inicial, nome, "Domingos · professores", selo da série atual, números (alunos, presença no mês, lição x de y), botão "Fazer chamada"; abas Visão geral | Lições | Alunos · N | Ajustes. Substitui ClassroomHeader/ClassroomTabs.
- Visão geral (classrooms/show, reescrita): hero da lição atual (série, nº, referência, desde quando e quantos domingos, "x de y já leram", Apresentar, Mensagem do grupo, Editar, Concluir e passar para a N); "A seguir na série" (3 próximas, com Rascunho/Pronta e ⋯); lateral "Precisam de atenção" (faltas seguidas + aniversário, WhatsApp) e "Última chamada". Estados vazios: sem lição atual → escolher; série acabou → concluir série / escolher próxima série.
- Lições (rota nova classes/{c}/licoes): busca, Nova série, Nova lição, Biblioteca oficial; acordeão de séries: atual aberta com barra "x de y estudadas", linha recolhida "N estudadas · Mostrar", linhas com nº, título, selo (Em estudo, Rascunho, Pronta, Estudada) e ⋯ (Estudar esta agora, Editar, Ver como aluno); outras séries fechadas; "Concluídas" no fim; "Avulsas" para lições sem série. ⋯ da série: Tornar série atual, Marcar como concluída/Reabrir, Nova lição nesta série, Editar série, atualização da biblioteca. Estado aberto/fechado salvo em localStorage.
- Alunos (students/index): tabela simples (avatar com iniciais, situação, últimos 8 domingos em quadradinhos, leu a lição atual) + filtros que já existem. Relatório como botão aqui.
- Página do aluno (students/show): coluna esquerda (perfil, situação, WhatsApp, Editar, presença %, lições lidas, telefone, aniversário, desde, último acesso, mudar de classe/remover); direita com abas Resumo | Presença | Estudos | Anotações.
- Ajustes: classrooms/form dentro do ClassroomShell (edição); criar classe continua fora.
- Chamada (meetings/show): mantém; "Mais" perde "Repetir a lição no próximo"; ao terminar pergunta se a lição terminou.
- Barra lateral: seletor de igreja, CLASSES (lista), embaixo Biblioteca oficial, Ajustes da igreja, Voltar ao app. "Lições"/"Séries" globais saem do menu (rotas ficam).
- Dashboard (várias classes): cada classe com a lição atual e o que falta.

## Entrega em PRs (cada merge vai para produção)
1. Modelo + ações + backfill + invariante do próximo encontro + rotas novas + testes (PHPUnit). Sem mudar telas além do mínimo ("2º domingo").
2. ClassroomShell + Visão geral + aba Lições (+ remover Planejar série / agenda).
3. Alunos (lista) + página do aluno + Ajustes na casca + barra lateral + dashboard.
4. MCP + skills + README + limpeza (PlanMeetings, ContinueLessonNextMeeting, ShiftPlannedLessons, meetings/index).

## Perguntas em aberto
- Manter "Sem EBD" (cancelar um domingo)? Hoje alimenta o aviso "não teremos EBD" na Home. Proposta: manter só no ⋯ da chamada.
- Badges que contam encontros planejados futuros da série (AwardBadges seriesMeetings) mudam de sentido: aceitar ou trocar por "lições da série"?
- "4 de 13" só conta lições existentes; série copiada da biblioteca já traz todas.

## Revisão após o Codex (2026-10-27) — substitui o que conflitar acima
- CurrentLessonQuery lê `current_lesson_id` direto (sem fallback que ressuscita a última realizada). O encontro só diz QUANDO: próximo encontro não cancelado da lição atual. Classe com current_lesson_id nulo e ainda não migrada usa a lógica antiga (compatibilidade durante a virada).
- Projeção: religa só encontros futuros/hoje SEM chamada, SEM finished_at e SEM título de evento. Depois da chamada/encerramento de hoje, projeta a próxima data livre (> hoje). Respeita "Sem EBD" (pula datas canceladas, não recria). ChangeMeetingWeekday continua movendo o encontro projetado e as leituras.
- "Fazer chamada": cria automaticamente só para HOJE (dia de encontro). Data passada só se o encontro já existir; sem encontro, o professor escolhe a lição.
- Lição apagada (soft delete): LessonController::destroy limpa o ponteiro e avança/zera explicitamente.
- Modelo: + `classrooms.current_lesson_started_at` ("em estudo desde"). `studied_at` explícito; reestudar NÃO apaga: status "Em estudo" tem precedência. Validar que lição/série pertencem à classe. Datas sempre dentro de ChurchCalendar::using(igreja).
- Concorrência: ações em transação com lockForUpdate na classe; AdvanceLesson recebe o id da lição esperada (clique duplo ou aba velha = nada acontece).
- Autorização: ações de lição/série atual com a permissão de conteúdo dos professores (manageContent), não ClassroomPolicy::update (só admin).
- Backfill vira comando artisan `ebd:licao-atual` com --dry-run, contagem por classe, idempotente, backup em JSON dos encontros removidos; remove só encontros futuros planned sem chamada, sem notas, sem título, sem finished_at, sem visitantes. concluded_at fica nulo (professor marca). studied_at inferido = aproximação.
- AwardBadges: selo de presença da série passa a ser dado ao CONCLUIR a série (reavalia alunos), não por "não há mais encontros planejados". Period: fim da série = max(ends_on, último encontro realizado) para não esconder aulas prolongadas. StudentProgressQuery/recent(): estudadas = studied_at, presença continua por encontros.
- LessonFactory::completed() e ->on() atualizados; testes para os limites (hoje antes/depois da chamada, Sem EBD, rascunho atual, lição apagada, duplo avanço, troca de dia, duas igrejas).

## Ordem de PRs revisada
1. Fundação (invisível): schema aditivo + ações novas + testes. Nada lê ainda.
2. Virada: leitores com compatibilidade (CurrentLessonQuery, StudyWeek/ReadingWeeks via projeção, lembretes, badges, Period, progresso), escritores antigos desligados (Planejar série, Repetir no próximo, meeting_on, MCP plan_meetings/continues/shift → novas set_current_lesson/advance_lesson), comando de backfill, + telas Visão geral e aba Lições (sem elas não dá para operar). Depois do deploy: dry-run em produção, conferir, rodar.
3. Alunos (lista) + página do aluno + Ajustes na casca + barra lateral + dashboard.
4. Limpeza do código morto + skills preparar/revisar-licao + README.
