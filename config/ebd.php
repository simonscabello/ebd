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
