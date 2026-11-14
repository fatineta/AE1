<?php
return [
    'host' => 'localhost',
    'db'   => 'CINE_DB',
    'user' => 'root',
    'pass' => '18.05.46.711.314.05',

    'tmdb_token' => 'eyJhbGciOiJIUzI1NiJ9.eyJhdWQiOiJjODYwNmEyMWQ2NzNiNTE5NGVjODhiNmJlYmU3NGY1ZCIsIm5iZiI6MTc5MzY1NzAzMy43Nzc5OTk5LCJzdWIiOiI2YWU5MDhjOTVmOTdlYTBlYmZhZjYxMzkiLCJzY29wZXMiOlsiYXBpX3JlYWQiXSwidmVyc2lvbiI6MX0.rXQMIDaa7eGvt74NyPiHen1L6P_5hSxL2wPsJRPXEAA',
    'tmdb_max'    => 20,     // cuantas peliculas traer

    // ===== SMTP para enviar el QR por correo =====
    'smtp_host'      => 'smtp.gmail.com',
    'smtp_port'      => 587,
    'smtp_secure'    => 'tls',
    'smtp_user'      => 'cinemalights1821@gmail.com',
    'smtp_pass'      => 'edcqxxspwtrdakxu',
    'smtp_from'      => 'cinemalights1821@gmail.com',
    'smtp_from_name' => 'Cinema Lights',

    'qr_secret'      => 'cinema_lights_secreto_Pa_2_0121_0518_nov',

    ];