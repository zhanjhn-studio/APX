<?php
declare(strict_types=1);
namespace App\Core;

/**
 * 路由分发。支持 {param} 参数匹配与每路由中间件。
 * 处理函数格式：ControllerClass@method，参数按名绑定。
 */
class Router
{
    private array $routes = [];

    public function __construct(private Request $request)
    {
    }

    public function load(array $routes): void
    {
        $this->routes = ['GET' => [], 'POST' => [], 'PUT' => [], 'DELETE' => [], 'HEAD' => [], 'OPTIONS' => [], 'ANY' => []];
        foreach ($routes as $r) {
            if (!is_array($r) || count($r) < 3) {
                continue;
            }
            $m = strtoupper((string) $r[0]);
            $entry = [
                'path' => $r[1],
                'handler' => $r[2],
                'middleware' => $r[3] ?? [],
            ];
            if ($m === 'ANY') {
                $this->routes['ANY'][] = $entry;
            } elseif (isset($this->routes[$m])) {
                $this->routes[$m][] = $entry;
            }
        }
    }

    public function dispatch(): void
    {
        $method = $this->request->method();
        $path = $this->request->routePath();

        $candidates = array_merge($this->routes[$method] ?? [], $this->routes['ANY'] ?? []);
        $matched = null;
        $params = [];
        foreach ($candidates as $route) {
            $p = $this->match($route['path'], $path);
            if ($p !== null) {
                $matched = $route;
                $params = $p;
                break;
            }
        }

        if ($matched === null) {
            abort(404);
            return;
        }

        foreach (($matched['middleware'] ?? []) as $mw) {
            $name = is_array($mw) ? $mw[0] : $mw;
            $args = is_array($mw) ? array_slice($mw, 1) : [];
            $class = 'App\\Middleware\\' . ucfirst($name) . 'Middleware';
            if (!class_exists($class)) {
                continue;
            }
            if (call_user_func([$class, 'handle'], $this->request, ...$args) === false) {
                return;
            }
        }

        [$ctrl, $action] = explode('@', $matched['handler']);
        $ctrlClass = 'App\\Controllers\\' . $ctrl;
        if (!class_exists($ctrlClass)) {
            abort(500, 'route.handler_missing');
            return;
        }
        $instance = new $ctrlClass();
        if (!method_exists($instance, $action)) {
            abort(500, 'route.action_missing');
            return;
        }

        $ref = new \ReflectionMethod($instance, $action);
        $args = [];
        foreach ($ref->getParameters() as $p) {
            $name = $p->getName();
            $type = $p->getType();
            $typeName = ($type instanceof \ReflectionNamedType) ? $type->getName() : '';

            if (array_key_exists($name, $params)) {
                // 路由参数：按目标类型做标量转换，避免 strict_types 下类型不符
                $value = $params[$name];
                if ($type instanceof \ReflectionNamedType && $type->isBuiltin()) {
                    switch ($type->getName()) {
                        case 'int':    $value = (int) $value; break;
                        case 'float':  $value = (float) $value; break;
                        case 'bool':   $value = (bool) $value; break;
                        case 'string': $value = (string) $value; break;
                    }
                }
                $args[] = $value;
            } elseif ($name === 'request' || $name === 'req' || $typeName === \App\Core\Request::class) {
                // 注入请求对象：兼容 $request / $req 命名，以及按 App\Core\Request 类型声明
                $args[] = $this->request;
            } elseif ($p->isOptional()) {
                $args[] = $p->isDefaultValueAvailable() ? $p->getDefaultValue() : null;
            } else {
                $args[] = null;
            }
        }
        call_user_func_array([$instance, $action], $args);
    }

    private function match(string $pattern, string $path): ?array
    {
        $regex = preg_replace_callback('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', function ($m) {
            return '(?P<' . $m[1] . '>[^/]+)';
        }, $pattern);
        $regex = '#^' . rtrim($regex, '/') . '/?$#';
        if (preg_match($regex, $path, $m)) {
            $params = [];
            foreach ($m as $k => $v) {
                if (is_string($k)) {
                    $params[$k] = $v;
                }
            }
            return $params;
        }
        return null;
    }
}
