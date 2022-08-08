<?php
namespace Core;

class Router
{
    public $routes;
    public $params;

    /**
     * Добавить маршрут
     * @param $route
     * @param array $params
     */
    public function add($route, $params = []){
        $route = preg_replace('/\//', '\\/', $route);
        $route = preg_replace('/\{([a-z]+)\}/', '(?P<\1>[a-z-]+)', $route);
        $route = preg_replace('/\{([a-z]+):([^\}]+)\}/', '(?P<\1>\2)', $route);
        $route = '/^' . $route . '$/i';

        $this->routes[$route] = $params;
    }

    /**
     * Получить массив со всеми маршрутами
     * @return mixed
     */
    public function getRoutes(){
        return $this->routes;
    }

    /**
     * Найти маршрут
     * @param $url
     * @return bool
     */
    public function match($url){
        foreach ($this->routes as $route => $params) {
            if (preg_match($route, $url, $matches)){
                foreach ($matches as $key => $match){
                    if (is_string($key)){
                        $params[$key] = $match;
                    }
                }

                $this->params = $params;
                return true;
            }
        }
        return false;
    }

    /**
     * Создать контроллер и вызвать action
     * @param $url
     * @throws \Exception
     */
    public function dispatch($url){
        $url = $this->removeQueryStringVariables($url);
        if ($this->match($url)){
            $namespace = $this->params['namespace'];
            $controller = $this->params['controller'];
            if ($namespace) {
                $controller = "App\Controllers\\$namespace\\$controller";
            }
            else {
                $controller = "App\Controllers\\$controller";
            }


            if (class_exists($controller)) {
                $controller_object = new $controller;

                $action = $this->params['action'];
                if (is_callable([$controller_object, $action])) {
                    if (isset($this->params['url'])) {
                        $controller_object->$action($this->params['url']);
                    }
                    else {
                        $controller_object->$action();
                    }
                }
                else {
                    throw new \Exception("Метод $action контроллера $controller не найден!");
                }
            }
            else {
                throw new \Exception("Контроллер $controller не найден!");
            }
        }
        else {
            throw new \Exception("404 Маршрут не найден", 404);
        }
    }

    /**
     * Убрать из урла параметры запроса
     * @param string $url
     * @return bool|string
     */
    protected function removeQueryStringVariables($url){
        if ($url != ''){
            $url = substr($url,1);

            if (strpos($url, '?') !== false){
                $url_array = explode('?', $url);
                $url = $url_array[0];
            }
        }

        return $url;
    }
}