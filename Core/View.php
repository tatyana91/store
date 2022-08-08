<?php
namespace Core;

class View
{
    public static function render($view, $args = [])
    {
        extract($args, EXTR_SKIP);
        $file = dirname(__DIR__) . "/App/Views/$view";
        if (is_readable($file)) {
            require $file;
        } else {
            throw new \Exception("$file not found");
        }
    }

    public static function renderTemplate($template, $args = [])
    {
        static $twig = null;
        if ($twig === null) {
            $loader = new \Twig_Loader_Filesystem(dirname(__DIR__) . '/App/Views');
            $twig = new \Twig_Environment($loader, array(
                'debug' => true
            ));
            $twig->addExtension(new \Twig_Extension_Debug());
        }
        echo $twig->render($template, $args);
    }

    public static function returnTemplate($template, $args = [])
    {
        static $twig = null;
        if ($twig === null) {
            $loader = new \Twig_Loader_Filesystem(dirname(__DIR__) . '/App/Views');
            $twig = new \Twig_Environment($loader, array(
                'debug' => true
            ));
            $twig->addExtension(new \Twig_Extension_Debug());
        }
        return $twig->render($template, $args);
    }
}