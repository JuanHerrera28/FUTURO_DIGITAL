<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * Enrutador mínimo con parámetros {id}, permisos y protección CSRF.
 */
final class Router
{
    /** @var list<array{method:string,path:string,handler:callable|array{0:class-string,1:string},permission:?string,guest:bool}> */
    private array $routes = [];

    /** @param callable|array{0:class-string,1:string} $handler */
    public function add(string $method, string $path, callable|array $handler, ?string $permission = null, bool $guest = false): void
    {
        $normalized = $path === '/' ? '/' : '/' . trim($path, '/');

        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $normalized,
            'handler' => $handler,
            'permission' => $permission,
            'guest' => $guest,
        ];
    }

    /** @param callable|array{0:class-string,1:string} $handler */
    public function get(string $path, callable|array $handler, ?string $permission = null, bool $guest = false): void
    {
        $this->add('GET', $path, $handler, $permission, $guest);
    }

    /** @param callable|array{0:class-string,1:string} $handler */
    public function post(string $path, callable|array $handler, ?string $permission = null, bool $guest = false): void
    {
        $this->add('POST', $path, $handler, $permission, $guest);
    }

    public function dispatch(): void
    {
        $path = Request::path();
        $method = Request::method();

        if ($method === 'POST') {
            $override = strtoupper(Request::string('_method'));
            if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                $method = $override;
            }
        }

        $match = null;
        $params = [];

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $pattern = $route['path'];
            $regex = preg_replace_callback(
                '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
                static fn (array $m): string => '(?P<' . $m[1] . '>[^/]+)',
                $pattern
            );

            if (preg_match('#^' . $regex . '$#', $path, $matches)) {
                $match = $route;
                foreach ($matches as $key => $value) {
                    if (is_string($key)) {
                        $params[$key] = ctype_digit($value) ? (int) $value : urldecode($value);
                    }
                }
                break;
            }
        }

        if ($match === null) {
            $pathExists = false;
            foreach ($this->routes as $route) {
                $regex = preg_replace('/\{[a-zA-Z_][a-zA-Z0-9_]*\}/', '[^/]+', $route['path']);
                if (preg_match('#^' . $regex . '$#', $path)) {
                    $pathExists = true;
                    break;
                }
            }

            $this->renderError($pathExists ? 405 : 404, $pathExists ? 'Método no permitido.' : 'La página solicitada no existe.');
            return;
        }

        try {
            if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
                if (!Csrf::validate(Request::input('_token'))) {
                    throw new HttpException(419, 'La sesión del formulario expiró o el token de seguridad no es válido. Recargue la página e intente de nuevo.');
                }
            }

            if ($match['guest']) {
                if (Auth::check()) {
                    Response::redirect('/dashboard');
                }
            } else {
                if (!Auth::check()) {
                    Session::set('_intended', $path);
                    Session::flash('warning', 'Debe iniciar sesión para continuar.');
                    Response::redirect('/login');
                }

                if (Auth::mustChangePassword() && !in_array($path, ['/change-password', '/logout'], true)) {
                    Session::flash('warning', 'Debe cambiar la contraseña temporal antes de continuar.');
                    Response::redirect('/change-password');
                }

                if ($match['permission'] !== null && !Auth::can($match['permission'])) {
                    throw new HttpException(403, 'No tiene permiso para realizar esta acción.');
                }
            }

            $handler = $match['handler'];

            if (is_array($handler)) {
                [$class, $action] = $handler;
                $controller = new $class();
                $controller->{$action}(...array_values($params));
                return;
            }

            $handler(...array_values($params));
        } catch (DomainException $e) {
            Session::flash('error', $e->fullMessage());
            Response::back('/dashboard');
        } catch (HttpException $e) {
            $this->renderError($e->status(), $e->getMessage());
        } catch (Throwable $e) {
            error_log('[Futuro Digital] ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString());
            $details = Config::get('app.debug', false)
                ? $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')'
                : null;
            $this->renderError(500, 'Ocurrió un error inesperado. El equipo técnico fue notificado.', $details);
        }
    }

    private function renderError(int $status, string $message, ?string $details = null): void
    {
        http_response_code($status);

        $view = is_file(BASE_PATH . '/views/errors/' . $status . '.php') ? 'errors.' . $status : 'errors.500';
        $layout = Auth::check() ? 'layouts/app' : 'layouts/auth';

        View::render($view, [
            'title' => 'Error ' . $status,
            'message' => $message,
            'details' => $details,
            'status' => $status,
        ], $layout);
    }
}
