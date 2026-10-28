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
    | Quantas igrejas ou grupos uma conta pode administrar antes de "Criar
    | minha igreja" recusar outra (a biblioteca oficial não conta).
    */
    'max_churches_per_account' => (int) env('EBD_MAX_CHURCHES_PER_ACCOUNT', 3),

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

    // Capa da lição: vai para o disco dos materiais, sempre em JPEG quadrado.
    // 1400 px é o mínimo que o Spotify aceita na imagem do episódio.
    'covers' => [
        'directory' => 'lesson-covers',
        'size' => 1400,
        // Miniatura dos cards e listas.
        'thumbnail_size' => 400,
        // Recorte sem o fundo papel (destaque do Início): lado maior.
        'cutout_size' => 900,
        'max_upload_kb' => 8 * 1024,
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
        'instructions' => env('EBD_AUDIO_INSTRUCTIONS', 'Fale em português do Brasil como um professor de escola bíblica animado, que ama o assunto e está contando uma descoberta para jovens: voz calorosa, enérgica e sorridente, nunca de leitura. Varie bastante a entonação, o volume e o ritmo: acelere um pouco nas partes de empolgação e desacelere nas ideias principais, dando ênfase forte às palavras-chave de cada frase. Perguntas soam como perguntas de verdade, com curiosidade; as frases de impacto ganham uma pausa curta antes e depois. Mude claramente o tom nas transições entre os tópicos. Expressivo e cheio de vida, mas sem exagero teatral.'),
        // Música de fundo (CC0, ver resources/audio/CREDITOS.md); precisa do ffmpeg.
        // Sem o arquivo ou sem o ffmpeg, o áudio sai só com a voz.
        'music' => env('EBD_AUDIO_MUSIC', resource_path('audio/fundo.mp3')),
        'music_volume' => (float) env('EBD_AUDIO_MUSIC_VOLUME', 0.18),
        // Estimativa de custo mostrada antes de gerar de novo (US$ por minuto de
        // áudio: voz ~0,015 + roteiro; ~900 caracteres por minuto narrado).
        // shortcut: preço fixo no config, conferir em developers.openai.com/api/docs/pricing quando mudar de modelo.
        'usd_per_minute' => (float) env('EBD_AUDIO_USD_PER_MINUTE', 0.017),
        'chars_per_minute' => 900,
        'usd_brl' => (float) env('EBD_USD_BRL', 5.5),
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
        'model' => env('EBD_HELPER_MODEL', 'gpt-5.6-luna'),
        'daily_limit' => (int) env('EBD_HELPER_DAILY_LIMIT', 10),
        'timeout' => (int) env('EBD_HELPER_TIMEOUT', 60),
    ],

    /*
    | Domínio próprio. canonical_host: o endereço principal (ex.:
    | usedidache.com.br). redirect_hosts: endereços antigos que levam para ele,
    | no mesmo caminho (ex.: ebd.up.railway.app,www.usedidache.com.br). Vazio,
    | nada é redirecionado.
    */
    // E-mail do dono no feed do podcast: o Spotify manda o código de confirmação para ele.
    'podcast_email' => env('EBD_PODCAST_EMAIL') ?: env('MAIL_FROM_ADDRESS'),

    'canonical_host' => env('EBD_CANONICAL_HOST') ?: null,
    'redirect_hosts' => array_values(array_filter(array_map(fn ($host) => strtolower(trim($host)), explode(',', (string) env('EBD_REDIRECT_HOSTS', ''))))),

];
