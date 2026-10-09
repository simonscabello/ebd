<?php

return [

    /*
    | Nome da igreja exibido na interface e no manifesto do PWA.
    */
    'church_name' => env('EBD_CHURCH_NAME', 'Nossa Igreja'),

    /*
    | Fuso horário da igreja. Usado para decidir o que é "hoje", qual é a
    | próxima aula e qual leitura destacar. O banco continua em UTC.
    */
    'timezone' => env('EBD_TIMEZONE', 'America/Sao_Paulo'),

    /*
    | Permite auto-cadastro. Contas novas não entram em nenhuma classe.
    */
    'registration_enabled' => (bool) env('EBD_REGISTRATION_ENABLED', true),

    /*
    | Contas (já cadastradas) promovidas a administrador por
    | `php artisan ebd:promote-admins`, executado no pre-deploy. Separe por vírgula.
    */
    'admin_emails' => array_values(array_filter(array_map('trim', explode(',', (string) env('EBD_ADMIN_EMAILS', ''))))),

    /*
    | Links pessoais de acesso dos alunos (enviados pelo WhatsApp).
    | remember_days: por quanto tempo o aparelho continua logado.
    | ttl_days: validade do link (vazio = até ser trocado ou bloqueado).
    */
    'access_links' => [
        'remember_days' => (int) env('EBD_ACCESS_LINK_REMEMBER_DAYS', 400),
        'ttl_days' => env('EBD_ACCESS_LINK_TTL_DAYS') ?: null,
    ],

    /*
    | Cadastro do aluno. Quem entra pelo link pessoal completa, no primeiro
    | acesso, e-mail, senha, WhatsApp, nascimento e gênero antes de usar o app.
    | required: desligue (false) só em emergência, para liberar o acesso.
    | password_reset_by_email: enquanto não houver provedor de e-mail em
    | produção, "Esqueci minha senha" orienta a pedir um novo link ao professor.
    */
    'onboarding' => [
        'required' => (bool) env('EBD_ONBOARDING_REQUIRED', true),
    ],

    'password_reset_by_email' => (bool) env('EBD_PASSWORD_RESET_BY_EMAIL', false),

    /*
    | "Alunos que precisam de atenção" no painel de evolução da classe.
    */
    'insights' => [
        'missed_meetings' => (int) env('EBD_RISK_MISSED_MEETINGS', 2),
        'inactive_days' => (int) env('EBD_RISK_INACTIVE_DAYS', 10),
    ],

    /*
    | Texto bíblico exibido junto das referências (texto base e leituras da
    | semana). Uma única versão, importada com `php artisan bible:import`.
    | O texto não fica no repositório: é da Sociedade Bíblica do Brasil.
    */
    'bible' => [
        'version' => env('EBD_BIBLE_VERSION', 'NAA'),
        'credit' => env('EBD_BIBLE_CREDIT', 'Nova Almeida Atualizada © Sociedade Bíblica do Brasil'),
    ],

    /*
    | Notificações push do PWA (Web Push). As chaves VAPID identificam este
    | servidor junto aos serviços de push; gere com `php artisan ebd:vapid-keys`.
    | Sem chaves, nada é enviado (as inscrições continuam sendo aceitas).
    */
    'push' => [
        'subject' => env('VAPID_SUBJECT', env('APP_URL', 'https://ebd.up.railway.app')),
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
    ],

    'materials' => [
        // Disco do config/filesystems.php. Troque para "s3" (S3, R2, MinIO) em produção.
        'disk' => env('EBD_MATERIALS_DISK', 'local'),
        'directory' => 'materials',
        // Limites em KB. Precisam caber em upload_max_filesize/post_max_size do PHP.
        'max_upload_kb' => (int) env('EBD_MAX_UPLOAD_KB', 30 * 1024),
        'max_audio_kb' => (int) env('EBD_MAX_AUDIO_KB', 60 * 1024),
        // Validade das URLs temporárias quando o disco suporta (S3/R2).
        'temporary_url_minutes' => 10,
    ],

    /*
    | Áudio do estudo ("Ouvir estudo"), narrado pela API de voz da OpenAI
    | (chave em OPENAI_API_KEY, só no servidor). O arquivo vai para o disco
    | dos materiais. voices: os narradores se revezam a cada parte do estudo
    | (introdução, tópicos principais, conclusão), na ordem da lista.
    | max_chars: tamanho de cada trecho enviado; textos maiores são divididos
    | por seção/parágrafo e os trechos viram um único MP3.
    */
    'audio' => [
        'model' => env('EBD_AUDIO_MODEL', 'gpt-4o-mini-tts-2025-12-15'),
        // Antes da voz, um modelo de texto reescreve cada parte para ser ouvida
        // (ListeningScript). false = narra o texto do estudo como está.
        'script' => (bool) env('EBD_AUDIO_SCRIPT', true),
        'script_model' => env('EBD_AUDIO_SCRIPT_MODEL', 'gpt-5.4-mini'),
        'voices' => array_values(array_filter(array_map('trim', explode(',', (string) env('EBD_AUDIO_VOICES', 'cedar,marin,ash'))))),
        'instructions' => env('EBD_AUDIO_INSTRUCTIONS', 'Fale em português do Brasil como um professor de escola bíblica que gosta do assunto e conversa com jovens: voz calorosa, envolvente e próxima, com entonação variada e ênfase nas ideias principais. Ritmo natural e fluido, nem lento nem apressado. Perguntas soam como perguntas de verdade, e as frases de impacto ganham uma pausa curta depois. Mude levemente o tom nas transições entre os tópicos. Expressivo, mas sem exagero teatral.'),
        'directory' => 'lesson-audio',
        'max_chars' => (int) env('EBD_AUDIO_MAX_CHARS', 3500),
        // Segundos de espera por trecho na OpenAI.
        'timeout' => (int) env('EBD_AUDIO_TIMEOUT', 180),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tirar dúvida
    |--------------------------------------------------------------------------
    | Na página da lição, membros da classe perguntam à IA (OpenAI, mesma
    | chave do áudio) sobre o estudo. Cada pergunta vai sozinha, com a lição
    | como contexto. daily_limit: perguntas por pessoa por dia (fuso da igreja).
    */
    'helper' => [
        'enabled' => (bool) env('EBD_HELPER_ENABLED', true),
        'model' => env('EBD_HELPER_MODEL', 'gpt-5.4-mini'),
        'daily_limit' => (int) env('EBD_HELPER_DAILY_LIMIT', 10),
        'timeout' => (int) env('EBD_HELPER_TIMEOUT', 60),
    ],

];
