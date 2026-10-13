<?php

namespace App\Core;

class Router
{
    protected $routes = [];
    protected $params = [];

    public function addRoute($route, $params = [], $method = 'GET')
    {
        // Convert route to regex pattern
        $routePattern = preg_replace('/\//', '\\/', $route);
        $routePattern = preg_replace('/\{([a-z_]+)\}/', '(?P<\1>[a-z-]+)', $routePattern);
        $routePattern = preg_replace('/\{([a-z_]+):([^\}]+)\}/', '(?P<\1>\2)', $routePattern);
        $routePattern = '/^' . $routePattern . '$/i';

        // Store method with route and use method+pattern as key to avoid overwrites
        $params['method'] = strtoupper($method);
        $routeKey = strtoupper($method) . '|' . $routePattern;
        $this->routes[$routeKey] = [
            'pattern' => $routePattern,
            'params' => $params
        ];
    }

    

    public function getRoutes()
    {
        return $this->routes;
    }

    public function match($url)
    {
        $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        
        // Handle method override for PUT/PATCH/DELETE
        if ($requestMethod === 'POST' && isset($_POST['_method'])) {
            $requestMethod = strtoupper($_POST['_method']);
        }
        
        foreach ($this->routes as $routeKey => $routeData) {
            $routePattern = $routeData['pattern'];
            $params = $routeData['params'];
            
            if (preg_match($routePattern, $url, $matches)) {
                // Check if HTTP method matches
                $routeMethod = $params['method'] ?? 'GET';
                if ($routeMethod !== $requestMethod && $routeMethod !== 'ANY') {
                    continue;
                }
                
                foreach ($matches as $key => $match) {
                    if (is_string($key)) {
                        $params[$key] = $match;
                    }
                }
                $this->params = $params;
                return true;
            }
        }
        return false;
    }

    public function getParams()
    {
        return $this->params;
    }

    public function dispatch($url)
    {
        $url = $this->removeQueryStringVariables($url);

        if ($this->match($url)) {
            $controller = $this->params['controller'];
            $controller = $this->convertToStudlyCaps($controller);
            $controller = $controller . 'Controller';
            $controller = $this->getNamespace() . $controller;

            if (class_exists($controller)) {
                $controller_object = new $controller($this->params);

                $action = $this->params['action'];
                $action = $this->convertToCamelCase($action);

                if (is_callable([$controller_object, $action])) {
                    $controller_object->$action();
                } else {
                    throw new \Exception("Method $action (in controller $controller) not found");
                }
            } else {
                throw new \Exception("Controller class $controller not found");
            }
        } else {
            $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            throw new \Exception("No route matched for URL: $url with method: $requestMethod", 404);
        }
    }

    protected function convertToStudlyCaps($string)
    {
        return str_replace(' ', '', ucwords(str_replace('-', ' ', $string)));
    }

    protected function convertToCamelCase($string)
    {
        return lcfirst($this->convertToStudlyCaps($string));
    }

    protected function removeQueryStringVariables($url)
    {
        if ($url != '') {
            $parts = explode('&', $url, 2);

            if (strpos($parts[0], '=') === false) {
                $url = $parts[0];
            } else {
                $url = '';
            }
        }
        return $url;
    }

    protected function getNamespace()
    {
        $namespace = 'App\Controllers\\';

        if (array_key_exists('namespace', $this->params)) {
            $namespace .= $this->params['namespace'] . '\\';
        }

        return $namespace;
    }
}

