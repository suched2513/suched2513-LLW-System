<?php

namespace App\Core;

/**
 * Class Router
 * จัดการแมปเส้นทาง URL (Routing) พร้อมรองรับ Middleware และ Route Parameters
 */
class Router
{
    private array $routes = [];
    private array $middlewares = [];

    public function registerMiddleware(string $name, string $middlewareClass): void
    {
        $this->middlewares[$name] = $middlewareClass;
    }

    public function get(string $path, array $handler, array $middleware = []): void
    {
        $this->addRoute('GET', $path, $handler, $middleware);
    }

    public function post(string $path, array $handler, array $middleware = []): void
    {
        $this->addRoute('POST', $path, $handler, $middleware);
    }

    private function addRoute(string $method, string $path, array $handler, array $middleware): void
    {
        $this->routes[] = [
            'method'     => $method,
            'path'       => '/' . trim($path, '/'),
            'handler'    => $handler,
            'middleware' => $middleware,
        ];
    }

    public function dispatch(Request $request): void
    {
        $method = $request->getMethod();
        $uri = $request->getUri();

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $routePattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $route['path']);
            $routePattern = '#^' . $routePattern . '$#';

            if (preg_match($routePattern, $uri, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                // Run Middlewares
                foreach ($route['middleware'] as $mw) {
                    $mwParts = explode(':', $mw, 2);
                    $mwName = $mwParts[0];
                    $mwArgs = isset($mwParts[1]) ? explode(',', $mwParts[1]) : [];

                    if (!isset($this->middlewares[$mwName])) {
                        Response::abort(500, "Middleware ไม่ถูกต้อง: {$mwName}");
                    }

                    $middlewareClass = $this->middlewares[$mwName];
                    $mwInstance = new $middlewareClass();
                    $mwInstance->handle($request, ...$mwArgs);
                }

                [$controllerClass, $action] = $route['handler'];
                if (!class_exists($controllerClass)) {
                    Response::abort(500, "ไม่พบคลาส Controller: {$controllerClass}");
                }

                $controller = new $controllerClass($request);
                if (!method_exists($controller, $action)) {
                    Response::abort(500, "ไม่พบเมธอด {$action} ใน {$controllerClass}");
                }

                call_user_func_array([$controller, $action], $params);
                return;
            }
        }

        Response::abort(404, "ไม่พบหน้าที่ต้องการ: {$uri}");
    }
}
