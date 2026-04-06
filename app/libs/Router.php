<?php

/**
 * Router + Request
 * @namespace Librerias
 * @version 3.00 – adaptado al proyecto Proyecto_intermodular
 *
 * Uso desde index.php:
 *   use Librerias\Router;
 *   $router = new Router();
 *   $router->add('GET', '/usuarios', UsuariosController::class, 'seleccionar');
 *   $router->dispatch();
 */

namespace Librerias;

class Router
{
    private array $routes = [];

    // ── Registrar una ruta ────────────────────────────────────────────────────
    public function add(string $method, string $path, string $controller, string $action): void
    {
        $this->routes[] = [
            'method'     => strtoupper($method),
            'path'       => $path,
            'controller' => $controller,
            'action'     => $action,
        ];
    }

    // ── Despachar la petición actual ──────────────────────────────────────────
    public function dispatch(): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $rawUri = urldecode($_SERVER['REQUEST_URI'] ?? '/');

        // Eliminar basePath (ej: /Proyecto_intermodular) y query string
        $uri = parse_url($rawUri, PHP_URL_PATH);
        $basePath = dirname($_SERVER['SCRIPT_NAME']);
        if ($basePath !== '/' && $basePath !== '\\') {
            $uri = substr($uri, strlen($basePath)) ?: '/';
        }

        // Asegurar que empieza con /
        if ($uri === '' || $uri[0] !== '/') {
            $uri = '/' . $uri;
        }

        foreach ($this->routes as $route) {
            $pattern = $this->convertPattern($route['path']);

            if ($route['method'] === $method && preg_match($pattern, $uri, $matches)) {
                // Parámetros del path ej: {id}
                array_shift($matches);
                $pathParams = $this->extractPathParams($route['path'], $matches);

                // Parámetros GET (?foo=bar)
                $queryParams = $_GET;

                // Body: JSON o form-data
                $bodyParams = $this->parseBody();

                // Objeto Request unificado
                $request = new Request($pathParams, $queryParams, $bodyParams);

                // Instanciar controlador y llamar la acción.
                // Estrategia de compatibilidad con los controllers existentes:
                //
                //   Sin params         → llama sin argumentos.        ej: cerrarSesion()
                //   Primer param=int   → resuelve ints de ruta/body.  ej: localizar(int $id)
                //   Primer param=array → pasa $allData fusionado.     ej: insertar(array $entrada)
                //   Primer param=Request → pasa el objeto Request.
                //
                // resolveInt($index, $name):
                //   1. Busca en pathParams por NOMBRE del parámetro. ej: {id_equipo} → int $id_equipo
                //   2. Busca en pathParams por POSICIÓN.             ej: primer {*} → param 0
                //   3. Busca en allData (query+body) por nombre.     ej: ?id_liga=3 o body {id_liga:3}
                $controller = new $route['controller']();
                $allData    = $request->all();
                $pathVals   = array_values($pathParams); // valores en orden de aparición en la ruta

                $resolveInt = function (int $index, string $name) use ($pathParams, $pathVals, $allData): int {
                    if (isset($pathParams[$name])) return (int)$pathParams[$name];   // por nombre
                    if (isset($pathVals[$index]))  return (int)$pathVals[$index];    // por posición
                    return (int)($allData[$name] ?? 0);                              // query / body
                };

                try {
                    $refMethod = new \ReflectionMethod($controller, $route['action']);
                    $reflParams = $refMethod->getParameters();
                } catch (\ReflectionException $e) {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Acción no encontrada: ' . $route['action']]);
                    return;
                }

                if (empty($reflParams)) {
                    // Sin argumentos: cerrarSesion(), etc.
                    call_user_func([$controller, $route['action']]);
                } else {
                    $firstType = $reflParams[0]->getType()?->getName() ?? 'mixed';

                    if ($firstType === 'int') {
                        // Construir lista de argumentos recorriendo todos los parámetros int al inicio
                        $args = [];
                        foreach ($reflParams as $i => $rp) {
                            $typeName = $rp->getType()?->getName() ?? 'mixed';
                            if ($typeName === 'int') {
                                $args[] = $resolveInt($i, $rp->getName());
                            } elseif ($typeName === 'array' || $typeName === '') {
                                // Resto de parámetros como array fusionado (ej: modificar(int $id, array $entrada))
                                $args[] = $allData;
                                break;
                            } else {
                                break;
                            }
                        }
                        call_user_func_array([$controller, $route['action']], $args);
                    } elseif ($firstType === 'array' || $firstType === '') {
                        // ej: seleccionar(array $entrada = []), insertar(array $entrada)
                        call_user_func([$controller, $route['action']], $allData);
                    } elseif (is_a($firstType, Request::class, true) || $firstType === 'Librerias\Request') {
                        // Controllers nuevos que usen Request directamente
                        call_user_func([$controller, $route['action']], $request);
                    } else {
                        // Fallback seguro: array fusionado
                        call_user_func([$controller, $route['action']], $allData);
                    }
                }

                return;
            }
        }

        // 404 si no hay coincidencia
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'Ruta no encontrada',
        ], JSON_UNESCAPED_UNICODE);
    }

    // ── Helpers privados ──────────────────────────────────────────────────────

    /** Convierte /usuarios/{id}/rol → regex ^/usuarios/([^/]+)/rol$ */
    private function convertPattern(string $path): string
    {
        $escaped = preg_quote($path, '/');
        $pattern = preg_replace('/\\\{([^\/]+)\\\}/', '([^\/]+)', $escaped);
        return '/^' . $pattern . '$/';
    }

    /** Empareja nombres de parámetros con valores capturados */
    private function extractPathParams(string $path, array $matches): array
    {
        preg_match_all('/\{([^\/]+)\}/', $path, $paramNames);
        $names = $paramNames[1];
        if (count($names) !== count($matches)) {
            return [];
        }
        return array_combine($names, $matches);
    }

    /** Lee el cuerpo de la petición (JSON preferente, luego form-data) */
    private function parseBody(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw) {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }
        // form-data / x-www-form-urlencoded
        return $_POST ?? [];
    }
}

// ── Request ───────────────────────────────────────────────────────────────────

class Request
{
    private array $pathParams;
    private array $queryParams;
    private array $bodyParams;

    public function __construct(array $pathParams, array $queryParams, array $bodyParams = [])
    {
        $this->pathParams  = $pathParams;
        $this->queryParams = $queryParams;
        $this->bodyParams  = $bodyParams;
    }

    // Parámetros de ruta ej: /usuarios/{id}
    public function getPathParam(string $name): mixed
    {
        return $this->pathParams[$name] ?? null;
    }

    public function getAllPathParams(): array
    {
        return $this->pathParams;
    }

    // Query string ej: ?page=2
    public function getQueryParam(string $name): mixed
    {
        return $this->queryParams[$name] ?? null;
    }

    public function getAllQueryParams(): array
    {
        return $this->queryParams;
    }

    // Body (JSON o form-data)
    public function getBodyParam(string $name): mixed
    {
        return $this->bodyParams[$name] ?? null;
    }

    public function getAllBodyParams(): array
    {
        return $this->bodyParams;
    }

    /**
     * Devuelve todos los parámetros fusionados: body tiene prioridad sobre query.
     * Útil para controllers que aceptan parámetros indiferentemente del origen.
     */
    public function all(): array
    {
        return array_merge($this->queryParams, $this->bodyParams, $this->pathParams);
    }
}
