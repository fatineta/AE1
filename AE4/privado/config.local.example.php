<?php
// Copiá este archivo como includes/config.local.php y completá tus datos.
// config.local.php está en .gitignore: NO se sube al repositorio.
// Recomendado: un usuario de MySQL dedicado (ver sql/usuario_app.sql) en vez de root.
return [
    'host' => 'localhost',
    'db'   => 'CINE_DB',
    'user' => 'root',
    'pass' => '18.05.46.711.314.05',

    // Token de acceso de lectura de TMDb (el largo). NO pegues ahi la api_key corta.
    'tmdb_token' => 'eyJhbGciOiJIUzI1NiJ9.eyJhdWQiOiJjODYwNmEyMWQ2NzNiNTE5NGVjODhiNmJlYmU3NGY1ZCIsIm5iZiI6MTc5MzY1NzAzMy43Nzc5OTk5LCJzdWIiOiI2YWU5MDhjOTVmOTdlYTBlYmZhZjYxMzkiLCJzY29wZXMiOlsiYXBpX3JlYWQiXSwidmVyc2lvbiI6MX0.rXQMIDaa7eGvt74NyPiHen1L6P_5hSxL2wPsJRPXEAA',
    'tmdb_max'    => 12,     // cuantas peliculas traer

];