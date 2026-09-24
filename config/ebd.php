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

];
