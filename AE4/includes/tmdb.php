<?php
// Clase para hablar con la API de TMDb usando el Token de acceso de lectura (Bearer).
// Se usa desde actualizar_peliculas.php y, si hace falta, desde algun api/*.php.

class TmdbService
{
    private string $token;
    private string $baseUrl = 'https://api.themoviedb.org/3';
    private string $idioma;
    private ?string $cacert;
    public ?string $ultimoError = null; // ultimo problema, para mostrarlo en pantalla

    public function __construct(string $token, string $idioma = 'es-AR', ?string $cacert = null)
    {
        $this->token = $token;
        $this->idioma = $idioma;
        $this->cacert = $cacert;
    }

    private function request(string $endpoint, array $params = []): ?array
    {
        $params['language'] = $params['language'] ?? $this->idioma;
        $url = $this->baseUrl . $endpoint . '?' . http_build_query($params);

        $this->ultimoError = null;

        $opciones = [
            "http" => [
                "method" => "GET",
                "header" => "Authorization: Bearer " . $this->token . "\r\n" .
                            "Accept: application/json\r\n" .
                            "User-Agent: Mozilla/5.0\r\n",
                "timeout" => 10,
                "ignore_errors" => true
            ],
            "ssl" => [
                "verify_peer" => $this->cacert !== null,
                "verify_peer_name" => $this->cacert !== null,
            ]
        ];

        if ($this->cacert && file_exists($this->cacert)) {
            $opciones["ssl"]["cafile"] = $this->cacert;
        }

        $contexto = stream_context_create($opciones);
        $respuesta = @file_get_contents($url, false, $contexto);

        if ($respuesta === false) {
            $this->ultimoError = "Error de conexion con TMDb a traves de file_get_contents.";
            error_log($this->ultimoError);
            return null;
        }

        // Obtener el código de estado HTTP de la respuesta de headers globales
        $codigo = 200;
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $header) {
                if (preg_match('#^HTTP/\S+\s+(\d+)#', $header, $matches)) {
                    $codigo = (int)$matches[1];
                    break;
                }
            }
        }

        if ($codigo !== 200) {
            $this->ultimoError = "TMDb respondio codigo $codigo para $endpoint";
            error_log($this->ultimoError);
            return null;
        }

        return json_decode($respuesta, true);
    }

    /** Busca una pelicula por titulo. Devuelve el primer resultado o null. */
    public function buscarPorTitulo(string $titulo, ?int $anio = null): ?array
    {
        $params = ['query' => $titulo];
        if ($anio) {
            $params['year'] = $anio;
        }
        $data = $this->request('/search/movie', $params);
        if (!$data || empty($data['results'])) {
            return null;
        }
        return $data['results'][0];
    }

    /** Trae el detalle completo (duracion, idioma original, sinopsis, etc.) */
    public function obtenerDetalle(int $idTmdb, array $extra = []): ?array
    {
        return $this->request('/movie/' . $idTmdb, $extra);
    }

    /** Peliculas que estan hoy en cartelera en un pais (region = codigo ISO, ej. AR, US). */
    public function enCartelera(string $region = 'AR', int $maxPeliculas = 20): array
    {
        $lista = [];
        for ($pagina = 1; $pagina <= 3 && count($lista) < $maxPeliculas; $pagina++) {
            $data = $this->request('/movie/now_playing', ['region' => $region, 'page' => $pagina]);
            if (!$data || empty($data['results'])) {
                break;
            }
            foreach ($data['results'] as $r) {
                $lista[] = $r;
            }
            if ($pagina >= ($data['total_pages'] ?? 1)) {
                break;
            }
        }
        return array_slice($lista, 0, $maxPeliculas);
    }

    /** Clasificacion por edad del pais pedido (ATP, +13, +16, +18) o null si TMDb no la tiene. */
    public function clasificacionDe(array $detalleConReleaseDates, string $region = 'AR'): ?string
    {
        foreach ($detalleConReleaseDates['release_dates']['results'] ?? [] as $pais) {
            if (($pais['iso_3166_1'] ?? '') !== $region) {
                continue;
            }
            foreach ($pais['release_dates'] ?? [] as $rd) {
                $c = strtoupper(trim($rd['certification'] ?? ''));
                if ($c === '') {
                    continue;
                }
                if ($c === 'ATP' || $c === 'TP') {
                    return 'ATP';
                }
                $n = (int) preg_replace('/\D/', '', $c);
                if (in_array($n, [13, 16, 18], true)) {
                    return '+' . $n;
                }
            }
        }
        return null;
    }

    /** Arma la URL completa del poster. $tamano puede ser w200, w342, w500, w780, original */
    public function urlPoster(?string $posterPath, string $tamano = 'w500'): ?string
    {
        if (!$posterPath) {
            return null;
        }
        return 'https://image.tmdb.org/t/p/' . $tamano . $posterPath;
    }

    /** Traduce el codigo de idioma de TMDb (es, en, fr...) a lo que usa pelicula.idioma */
    public function idiomaLegible(?string $codigoIso): string
    {
        $mapa = [
            'es' => 'Espanol',
            'en' => 'Ingles',
            'fr' => 'Frances',
            'it' => 'Italiano',
            'pt' => 'Portugues',
            'ja' => 'Japones',
            'ko' => 'Coreano',
        ];
        return $mapa[$codigoIso] ?? 'Espanol';
    }
}