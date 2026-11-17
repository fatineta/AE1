🎬 Cinema Lights

Sistema web de venta de entradas y combos de cine, con una guía visual de retiro que acompaña al cliente después de la compra.

Universidad de la Cuenca del Plata · Ingeniería en Sistemas de Información Paradigmas y Lenguajes de la Programación III · Comisión A · Prof. Dante Sicardi
Alumnas: Agazzoni, Fátima · Leguizamón, Vanina


📌 El problema

La web del cine de la ciudad es poco atractiva y, además, retirar la entrada y la comida con un código/QR en una máquina de autoatención resulta confuso para quienes no están familiarizados con estos sistemas.

Cinema Lights propone:

Una experiencia de compra moderna e intuitiva: cartelera → función → asientos → combos → pago.
Una guía de retiro paso a paso al finalizar la compra, y un QR firmado enviado por correo.

✨ Funcionalidades
Cartelera y ficha de cada película (datos sincronizables desde TMDb).
Consulta de horarios por fecha y película.
Mapa de butacas en tiempo real (disponible / reservado / ocupado).
Combos de comida y bebida (opcionales) y cupón de descuento validado en servidor.
Compra transaccional (sin doble venta de butacas) con código CL-xxxxx y QR únicos.
Envío del código y QR por correo (PHPMailer / SMTP) y reenvío desde "Mis compras".
Guía "¿Cómo retiro mi entrada y comida?" al finalizar la compra.
Registro, inicio de sesión y cierre de sesión.
Panel del personal: validación y canje de entradas (validar.html) y métricas (metricas.html).
Interfaz responsive y accesible (WCAG 2.1).

🧱 Arquitectura

MVC desacoplado: el cliente solo habla HTTP/JSON con la API.

┌─────────────────────────┐
│ VISTA                   │  HTML + style.css + app.js (JS Vanilla, fetch / async-await)
└────────────┬────────────┘
             │ HTTP + JSON
┌────────────▼────────────┐
│ CONTROLADOR             │  api/*.php  (validación, sesión, códigos HTTP)
└────────────┬────────────┘
             │ PDO (sentencias preparadas)
┌────────────▼────────────┐
│ MODELO / PERSISTENCIA   │  includes/db.php + esquema MySQL (CHECK, UNIQUE, FK, vistas, triggers)
└─────────────────────────┘
Estructura del proyecto
.
├── index.html, listado_box.html, listado_tabla.html, producto.html
├── asientos.html, combos.html, comprar.html          # flujo de compra
├── login.html, mis_compras.html                      # cuenta del cliente
├── validar.html, metricas.html                       # personal / administrador
├── app.js, style.css                                 # cliente y estilos
├── api/                                              # endpoints REST (controladores)
├── includes/
│   ├── db.php                  # conexión PDO
│   ├── sesion.php              # configuración de la cookie de sesión
│   ├── qr.php, qr_url.php      # código de reserva, firma HMAC y QR
│   ├── mailer.php              # envío de correo (PHPMailer)
│   ├── tmdb.php                # cliente de TMDb
│   ├── config.local.example.php
│   └── lib/                    # PHPMailer y generador de QR
├── sql/                        # esquema y migraciones
├── data/combos.json
├── privado/                    # datos no servidos por web (.htaccess)
├── sincronizar_cartelera.php, generar_funciones.php, actualizar_peliculas.php
└── info.php

🛠️ Requisitos
Componente	Versión recomendada
PHP	7.4 o superior (extensiones pdo_mysql, mbstring, gd)
MySQL / MariaDB	MySQL 8.0.16+ o MariaDB 10.2.1+ (se necesitan los CHECK y triggers)
Servidor web	Apache (XAMPP en Windows)

🚀 Instalación (XAMPP)
Clonar el repositorio dentro de htdocs:
bash
cd C:/xampp/htdocs
git clone [COMPLETAR: URL del repositorio]
Iniciar Apache y MySQL desde el panel de XAMPP.
Crear la base de datos importando (phpMyAdmin o consola) el script principal:
bash
mysql -u root -p < sql/CINE_DB_AE4_final.sql

⚠️ El script ejecuta DROP DATABASE IF EXISTS CINE_DB: borra y recrea la base.

Aplicar las migraciones en este orden, una sola vez cada una:
bash
mysql -u root -p < sql/migrar_peliculas_reales.sql
mysql -u root -p < sql/tmdb_cartelera.sql
mysql -u root -p < sql/ampliar_asientos_filas_A_I.sql

paso1_bcrypt.sql solo hace falta si la base viene de una versión anterior con contraseñas SHA-256. 5. Crear la tabla de visitas (la usan api/visita.php y el panel de métricas):

sql
[COMPLETAR: el script de la tabla Visita no figura en /sql; agregarlo y referenciarlo aquí]
Configurar las credenciales locales (ver siguiente sección).
Cargar datos de demo (opcional):
bash
php sincronizar_cartelera.php     # trae la cartelera desde TMDb y programa funciones
php generar_funciones.php 14      # genera funciones y butacas para los próximos 14 días
Abrir http://localhost/<carpeta-del-proyecto>/index.html.
⚙️ Configuración

Copiá includes/config.local.example.php como includes/config.local.php y completá tus datos. Ese archivo está en .gitignore: no se sube al repositorio.

php
<?php
return [
    // Base de datos
    'host' => 'localhost',
    'db'   => 'CINE_DB',
    'user' => 'root',            // recomendado: usuario dedicado (sql/usuario_app.sql)
    'pass' => '',

    // TMDb (token de acceso de lectura, el largo)
    'tmdb_token' => 'TU_TOKEN',
    'tmdb_max'   => 20,

    // Correo (SMTP) para enviar el QR
    'smtp_host'      => 'smtp.gmail.com',
    'smtp_port'      => 587,
    'smtp_secure'    => 'tls',
    'smtp_user'      => 'tu_cuenta@gmail.com',
    'smtp_pass'      => 'contraseña de aplicación',
    'smtp_from'      => 'tu_cuenta@gmail.com',
    'smtp_from_name' => 'Cinema Lights',

    // Clave para firmar los QR (si se omite, se genera en privado/qr_secret.key)
    'qr_secret' => 'una_clave_larga_y_aleatoria',

    // URL del sitio tal como la ve el CELULAR (no "localhost")
    'public_base_url' => 'http://192.168.1.35/<carpeta-del-proyecto>',
];

🔒 Nunca subas tokens, contraseñas SMTP ni qr_secret al repositorio. Si alguna credencial se expuso, rotala.

Para usar un usuario de MySQL con permisos mínimos, editá la clave en sql/usuario_app.sql y ejecutalo.

👤 Cuentas de prueba

El script principal carga usuarios de ejemplo con contraseñas BCRYPT:

Rol	Email	Contraseña
Administrador (personal)	admin@cine.com	[COMPLETAR]
Cliente	lucia@mail.com	[COMPLETAR]
Cliente	marcos@mail.com	[COMPLETAR]

Los registros públicos siempre se crean con rol cliente.

🔌 API REST

Todas las respuestas son JSON. Rutas relativas a la raíz del proyecto.

Método	Endpoint	Descripción	Acceso
GET	/api/peliculas.php[?slug=]	Cartelera o detalle de una película	Público
GET	/api/funciones.php?desde=&dias=&slug=	Funciones por fecha	Público
GET	/api/asientos.php?funcion=	Butacas de una función y su estado	Público
GET	/api/combos.php	Catálogo de comida y bebida	Público
POST	/api/registro.php	Alta de cliente	Público
POST	/api/login.php	Inicio de sesión	Público
POST	/api/logout.php	Cierre de sesión	Sesión
POST	/api/comprar.php	Compra de butacas y combos; genera código y QR	Cliente
GET	/api/mis_compras.php	Historial del cliente	Cliente
POST	/api/reenviar_qr.php	Reenvío del QR por correo	Cliente
GET/POST	/api/validar_ticket.php	Valida (GET) o canjea (POST) un QR	Firma HMAC / Administrador
GET/POST	/api/ver_ticket.php	Consulta (GET) o marca como utilizado (POST)	Firma HMAC / Administrador
GET	/api/metricas.php	Ventas y visitas	Administrador
POST	/api/visita.php	Suma una visita diaria	Público

Códigos HTTP usados: 200, 201, 204, 400, 401, 403, 404, 405, 409, 415, 429, 500, 502.

🔐 Seguridad (OWASP)
Contraseñas con BCRYPT (password_hash / password_verify).
PDO con sentencias preparadas (ATTR_EMULATE_PREPARES => false).
Cookie de sesión HttpOnly + SameSite=Lax (+ Secure bajo HTTPS) y session_regenerate_id al iniciar sesión.
Límite de 5 intentos de login cada 10 minutos (429).
Roles validados en el servidor; el id_cliente siempre sale de la sesión.
Precios, cupones y totales calculados en el servidor, dentro de una transacción con SELECT … FOR UPDATE.
QR con firma HMAC: el pedido siempre se lee de la base, nunca del texto del QR.
Salida al DOM con .textContent (mitigación de XSS).
.htaccess que bloquea el acceso directo a includes/ y privado/; credenciales fuera de Git.
♿ Accesibilidad (WCAG 2.1)
:focus-visible con contorno 3px solid #b91c1c en enlaces, botones, inputs, selects y textareas (style.css).
tabindex="0", aria-live="polite" y role="status" en el resumen y la confirmación de compra (comprar.html).
Menú hamburguesa con aria-expanded y aria-label dinámicos (app.js).
Páginas con lang="es", viewport y diseño responsive.
Auditoría Lighthouse (Accesibilidad): 100/100 — [COMPLETAR: capturas en docs/].

✅ Auditoría ISO/IEC 25010
#	Característica	Evidencia técnica	Estado
1	Adecuación funcional	CRUD en PHP que respalda los casos de uso de AE1 y persiste en MySQL vía PDO	✅
2	Eficiencia de desempeño	Respuestas JSON livianas, sin recargas totales	✅
3	Compatibilidad	Frontend JS Vanilla desacoplado de la API RESTful PHP	✅
4	Fiabilidad	try/catch de PDO, transacciones con rollBack y códigos HTTP semánticos	✅
5	Seguridad (OWASP)	BCRYPT, Prepared Statements y control de sesión con $_SESSION	✅
6	Usabilidad / Accesibilidad	Lighthouse 100/100, :focus-visible y regiones aria-live	✅
7	Mantenibilidad	Capas MVC, nombres expresivos y versionado en Git	✅

🧰 Scripts de administración
Script	Uso
sincronizar_cartelera.php	Sincroniza Pelicula con la cartelera de TMDb y programa funciones. Solo CLI o localhost.
generar_funciones.php	Genera funciones y butacas para los próximos días (php generar_funciones.php 14). Solo CLI o localhost.
actualizar_peliculas.php	Actualiza duración, idioma, imagen y descripción desde TMDb. Ejecutar a mano.
info.php	phpinfo() para diagnóstico. Eliminar antes de publicar el sitio.

📝 Limitaciones conocidas
El pago se registra como medio_de_pago; no hay integración con una pasarela real.
La guía de retiro se muestra al finalizar la compra; [COMPLETAR: indicar si existe acceso posterior desde el correo o "Mis compras"].
La protección CSRF se apoya en SameSite=Lax (sin token anti-CSRF).
Algunas consultas usan nombres de tabla en minúsculas (comida_bebida, pelicula) mientras el esquema usa Comida_Bebida, Pelicula: funciona en Windows/XAMPP, pero en Linux conviene unificar.
