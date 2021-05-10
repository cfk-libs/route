<?php


namespace Sellony\Route\Annotation;

use Slim\App;
use RecursiveDirectoryIterator;
use RecursiveRegexIterator;
use DateTime;

class CacheAnnotation
{
    private $cachepath;

    /**
     * @var App
     */
    private $application;

    /**
     * CacheSlimAnnotation constructor.
     *
     * @param string $cachepath
     * @param App $app
     */
    public function __construct(string $cachepath, App $app)
    {
        $this->cachepath = $cachepath;
        $this->application = $app;
    }

    public function write(array $routes)
    {
        $files = glob("$this->cachepath/*.*");

        if (count($files) > 0) {
            array_map('unlink', $files);
        }

        $name = 'CachedRoutes' . (new DateTime('now'))->format('YmdHis');
        $content = '';

        $arrayEscape = [
            '\\n' => "\\\\n", '\\r' => "\\\\r",
            '\\t' => "\\\\t", '\\v' => "\\\\v",
            '\\e' => "\\\\e"
        ];

        $template = file_get_contents(
            constant('Entrypoint') . '/app/Kernel/Library/Route/Annotation/RouteCacheTemplate.php'
        );

        $template = str_replace(
            '{{ROUTE-ANNOTATION-CLASSNAME}}',
            $name,
            $template
        );

        foreach ($routes as $routeModel) {
            $content .= sprintf(
                <<<ROUTE
                \$route = \$app->map(["%s"], "%s", "%s:%s");\n
            ROUTE,
                $routeModel->getVerb(),
                $routeModel->getRoute(),
                strtr($routeModel->getClassName(), $arrayEscape),
                $routeModel->getMethodName()
            );

            if ($routeModel->getAlias() != null) {
                $content .= sprintf(
                    <<<ROUTE
                    \$route->setName("%s");\n
                ROUTE,
                    $routeModel->getAlias()
                );
            }

            if ($routeModel->getClassMiddleware() != null) {
                foreach ($routeModel->getClassMiddleware() as $middleware) {
                    $content .= sprintf(
                        <<<ROUTE
                        \$route->add(new \\%s());
                    ROUTE,
                        $middleware
                    );
                }
            }
        }

        $template = str_replace(
            '{{ROUTE-ANNOTATION-CONTENT}}',
            $content,
            $template
        );

        $template = str_replace(
            '{{ARRAY-CONTROLLERS}}',
            serialize($routes),
            $template
        );

        return file_put_contents(
            $this->cachepath . '/' . $name . '.php',
            $template
        );
    }

    /**
     * @param array $controlerArray
     * @return bool
     * TODO Refactor chegar a
     */
    public function updatedCache(array $controlerArray, array $arrayRouteObject) : bool
    {
        if (config('app.env') == 'production') {
            if (!static::isDirEmpty($this->cachepath)) {
                return true;
            }
        }

        if (!file_exists($this->cachepath)) {
            mkdir($this->cachepath);
        }

        uasort(
            $controlerArray,
            [
                CacheAnnotation::class,
                "orderArrayByDateModified"
            ]
        );

        $directory = new RecursiveDirectoryIterator(
            $this->cachepath
        );

        $regexDirectory = new RecursiveRegexIterator(
            $directory,
            '/CachedRoutes(\d*)\.php/',
            RecursiveRegexIterator::GET_MATCH
        );

        $arrayDirectoryRegex = [];
        foreach ($regexDirectory as $item) {
            $arrayDirectoryRegex[] = [
                $item,
                filemtime($this->cachepath . DIRECTORY_SEPARATOR . $item[0])
            ];
        }

        if (count($arrayDirectoryRegex) == 0 ||
            serialize($arrayRouteObject) !== $this->validateControllerExcluded()
        ) {
            return false;
        }

        uasort(
            $arrayDirectoryRegex,
            [
                CacheAnnotation::class,
                "orderArrayByDateModified"
            ]
        );

        $firstControllerArray = array_shift($controlerArray);
        $firstDirectoryRegex = array_shift($arrayDirectoryRegex);

        if ($firstControllerArray[1] > $firstDirectoryRegex[1]) {
            return false;
        }

        return true;
    }

    private function orderArrayByDateModified(array $elementA, array $elementB)
    {
        if ($elementA == $elementB) {
            return 0;
        }

        return ($elementA[1] > $elementB[1]) ? -1 : 1;
    }

    /**
     * Check if a directory is empty (a directory with just '.svn' or '.git' is empty)
     * @param string $dirname
     * @return bool
     */
    public static function isDirEmpty($dirname)
    {
        foreach (scandir($dirname) as $file) {
            if (!in_array($file, ['.','..','.svn','.git'])) {
                return false;
            }
        }

        return true;
    }

    private function loadClassLastCache()
    {
        if (config('app.env') == 'production') {
            $filename = explode('.', scandir($this->cachepath)[2]);
            return 'Admin\App\Http\Routes\Cache\\' . $filename[0];
        }

        $directory = new RecursiveDirectoryIterator(
            $this->cachepath
        );

        $regexDirectory = new RecursiveRegexIterator(
            $directory,
            '/CachedRoutes(\d*)\.php/',
            RecursiveRegexIterator::GET_MATCH
        );

        $arrayDirectoryRegex = [];
        foreach ($regexDirectory as $item) {
            $arrayDirectoryRegex[] = $item;
        }

        uasort(
            $arrayDirectoryRegex,
            [
                CacheAnnotation::class, "orderArrayByDateModified"
            ]
        );

        $firstDirectoryRegex = array_shift($arrayDirectoryRegex);

        return "Admin\App\Http\Routes\Cache\\" . substr($firstDirectoryRegex[0], 0, strlen($firstDirectoryRegex[0]) - 4);
    }

    public function loadLastCache()
    {
        $classCache = $this->loadClassLastCache();

        $classCacheConcret  = new $classCache();
        $classCacheConcret($this->application);

        return $classCache;
    }

    public function validateControllerExcluded()
    {
        $classCache = $this->loadClassLastCache();

        $classCacheConcret  = new $classCache();
        return $classCacheConcret->getArrayControllersSerialize();
    }
}
