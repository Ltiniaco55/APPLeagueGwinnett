<?php

namespace Librerias;

class Router
{
    private array $routes = [];

    public function add(string $method, string $path, string $controller, string $action): void
    {
        $this->routes[] = [
            'method'     => strtoupper($method),
            'path'       => $path,
            'controller' => $controller,
            'action'     => $action,
        ];
    }

    public function dispatch(): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $rawUri = urldecode($_SERVER['REQUEST_URI'] ?? '/');

        $uri = parse_url($rawUri, PHP_URL_PATH);
        $basePath = dirname($_SERVER['SCRIPT_NAME']);
        if ($basePath !== '/' && $basePath !== '\\') {
            $uri = substr($uri, strlen($basePath)) ?: '/';
        }

        if ($uri === '' || $uri[0] !== '/') {
            $uri = '/' . $uri;
        }

        foreach ($this->routes as $route) {
            $pattern = $this->convertPattern($route['path']);

            if ($route['method'] === $method && preg_match($pattern, $uri, $matches)) {
                array_shift($matches);
                $pathParams = $this->extractPathParams($route['path'], $matches);

                $queryParams = $_GET;

                $bodyParams = $this->parseBody();

                $request = new Request($pathParams, $queryParams, $bodyParams);

                $controller = new $route['controller']();
                $allData    = $request->all();
                $pathVals   = array_values($pathParams);

                $resolveInt = function (int $index, string $name) use ($pathParams, $pathVals, $allData): int {
                    if (isset($pathParams[$name])) return (int)$pathParams[$name];
                    if (isset($pathVals[$index]))  return (int)$pathVals[$index];
                    return (int)($allData[$name] ?? 0);
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
                    call_user_func([$controller, $route['action']]);
                } else {
                    $firstType = $reflParams[0]->getType()?->getName() ?? 'mixed';

                    if ($firstType === 'int') {
                        $args = [];
                        foreach ($reflParams as $i => $rp) {
                            $typeName = $rp->getType()?->getName() ?? 'mixed';
                            if ($typeName === 'int') {
                                $args[] = $resolveInt($i, $rp->getName());
                            } elseif ($typeName === 'array' || $typeName === '') {
                                $args[] = $allData;
                                break;
                            } else {
                                break;
                            }
                        }
                        call_user_func_array([$controller, $route['action']], $args);
                    } elseif ($firstType === 'array' || $firstType === '') {
                        call_user_func([$controller, $route['action']], $allData);
                    } elseif (is_a($firstType, Request::class, true) || $firstType === 'Librerias\Request') {
                        call_user_func([$controller, $route['action']], $request);
                    } else {
                        call_user_func([$controller, $route['action']], $allData);
                    }
                }

                return;
            }
        }

        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'Ruta no encontrada',
        ], JSON_UNESCAPED_UNICODE);
    }

    private function convertPattern(string $path): string
    {
        $escaped = preg_quote($path, '/');
        $pattern = preg_replace('/\\\{([^\/]+)\\\}/', '([^\/]+)', $escaped);
        return '/^' . $pattern . '$/';
    }

    private function extractPathParams(string $path, array $matches): array
    {
        preg_match_all('/\{([^\/]+)\}/', $path, $paramNames);
        $names = $paramNames[1];
        if (count($names) !== count($matches)) {
            return [];
        }
        return array_combine($names, $matches);
    }

    private function parseBody(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw) {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }
        return $_POST ?? [];
    }
}

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

    public function getPathParam(string $name): mixed
    {
        return $this->pathParams[$name] ?? null;
    }

    public function getAllPathParams(): array
    {
        return $this->pathParams;
    }

    public function getQueryParam(string $name): mixed
    {
        return $this->queryParams[$name] ?? null;
    }

    public function getAllQueryParams(): array
    {
        return $this->queryParams;
    }

    public function getBodyParam(string $name): mixed
    {
        return $this->bodyParams[$name] ?? null;
    }

    public function getAllBodyParams(): array
    {
        return $this->bodyParams;
    }

    public function all(): array
    {
        return array_merge($this->queryParams, $this->bodyParams, $this->pathParams);
    }
}
