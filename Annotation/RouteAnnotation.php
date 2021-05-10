<?php

/**
 * @package Sellony Api | Channel::Admin
 * @author Cemre Fatih Karakulak <cradexco@gmail.com>
 */

namespace Sellony\Router\Annotation;

use Slim\App;
use Sellony\Router\Annotation\RouteAnnotation;

class RouteAnnotation
{
    public static $cache_path;

    /**
     * @param App $application
     * @param array $arrayController
     * @param string $pathCache
     * @throws \Exception
     */
    public static function create(App $application, array $arrayController, string $pathCache)
    {
        self::createAutoloadCache($pathCache);

        $arrayRoute = [];
        $arrayRouteObject = [];

        foreach ($arrayController as $pathController) {
            $collector = new CollectorRoute();

            if (count($arrayRoute) == 0) {
                $arrayRoute = $collector->getControllers($pathController);
            } else {
                $arrayMerged = $collector->getControllers($pathController);

                foreach ($arrayMerged as $element) {
                    array_push($arrayRoute, $element);
                }
            }
        }

        $arrayRouteObject = $collector->castRoute($arrayRoute);

        self::injectRoute($application, $arrayRouteObject, $arrayRoute, $pathCache);
    }

    public static function createAutoloadCache($pathCache)
    {
        self::$cache_path = $pathCache;

        spl_autoload_register([
            RouteAnnotation::class, 'loadClassAutoload'
        ]);
    }

    public static function loadClassAutoload($class)
    {
        $extension = ".php";
        $class = str_replace("Cache\\", "", $class);
        $file = str_replace("\\", DIRECTORY_SEPARATOR, self::$cache_path . DIRECTORY_SEPARATOR . $class . $extension);

        if (file_exists($file)) {
            include $file;
        }
    }

    private static function injectRoute(App $application, array $arrayRouteObject, array $arrayRoute, string $pathCache)
    {
        $validate = new CacheAnnotation(
            $pathCache,
            $application
        );

        if ($validate->updatedCache($arrayRoute, $arrayRouteObject)) {
            $validate->loadLastCache();
        } else {
            foreach ($arrayRouteObject as $routeModel) {
                $route = $application->map(
                    [
                        $routeModel->getVerb()
                    ],
                    $routeModel->getRoute(),
                    $routeModel->getClassName() . ':' . $routeModel->getMethodName()
                );

                if ($routeModel->getAlias() != null) {
                    $route->setName($routeModel->getAlias());
                }

                if ($routeModel->getClassMiddleware() != null) {
                    foreach ($routeModel->getClassMiddleware() as $middleware) {
                        $route->add(new $middleware());
                    }
                }
            }

            $validate->write($arrayRouteObject);
        }
    }
}
