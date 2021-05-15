<?php

namespace Sellony\Route\Annotation;

use Slim\App;
use Illuminate\Support\Collection;
use ReflectionClass;
use Exception;

class Annotator
{
    public string $path;

    public function __construct(
        public App $app,
        public array $files
    ) {}

    public function compile(string $path)
    {
        return $this->inject($this->cast($this->files), $path);
    }

    public function cast(array $files) : Collection
    {
        $collection = collect();

        foreach ($files as $item) {
            $content = $item->getContents();

            preg_match('/namespace\s+([\w\\\_-]+)\s*;/', $content, $namespace);
            preg_match('/class\s+([\w-]+Controller)\s*/', $content, $classname);

            $class = new ReflectionClass(
                sprintf('%s\%s', $namespace[1], $classname[1])
            );

            preg_match('/@Route\s*\(\s*["\']([^\'"]*)["\']\s*\)/', $class->getDocComment(), $prefix);

            foreach ($class->getMethods() as $methods) {
                preg_match('/@([a-zA-Z]*)\s*\((.*)\)/', $methods->getDocComment(), $route);

                if (count($route) == 0) {
                    continue 1;
                }

                preg_match('/name\s{0,}=\s{0,}["\']([^\'"]*)["\']/', $route[2], $name);
                preg_match('/alias\s{0,}=\s{0,}["\']([^\'"]*)["\']/', $route[2], $alias);
                preg_match('/middleware\s{0,}=\s{0,}\{(.*?)\}/', $route[2], $middlewares);

                if (count($name) == 0) {
                    continue 1;
                }

                if (count($middlewares) > 0) {
                    preg_match_all('/\"(.*?)\"/', $middlewares[1], $middleware);

                    $middlewares = [];
                    foreach ($middleware[1] as $item) {
                        if (trim($item) == "" || !class_exists(trim($item))) {
                            throw new Exception('Annotation of poorly written middleware. Class: ' . $class->getName());
                        }

                        $middlewares[] = trim($item);
                    }
                }

                try {
                    $arrayVerb = ['GET', 'POST', 'OPTIONS', 'DELETE', 'PATCH', 'ANY', 'PUT'];
                    $verb = strtoupper($route[1]);

                    if (!in_array($verb, $arrayVerb)) {
                        throw new Exception('Parameter verb is not defined in the HTTP verbs');
                    }
                } catch (Exception $e) {
                    continue 1;
                }

                $collection->push(
                    collect([
                        'verb' => $verb,
                        'path' => sprintf('%s%s%s', config('app.url_prefix'), $prefix[1], $name[1]),
                        'class' => $class->getName(),
                        'method' => $methods->getName(),
                        'alias' => count($alias) > 0 ? $alias[1] : null,
                        'middlewares' => count($middlewares) > 0 ? $middlewares : [],
                    ])
                );
            }

            if (ob_get_contents()) {
                ob_clean();
            }
        }

        return $collection;
    }

    public function inject(Collection $routes, string $path)
    {
        $this->path = $path;

        $template = file_get_contents(__DIR__ . '/stubs/routes.stub');

        $routes = $routes->sortBy(function($item) {
            return strpos($item->get('path'), '{') !== false;
        });

        $content = [];
        foreach ($routes as $item) {
            $content[] = sprintf(
                "\t\$route = \$app->map(['%s'], '%s', '%s:%s');\n",
                $item->get('verb'),
                $item->get('path'),
                strtr($item->get('class'), [
                    '\\n' => "\\\\n", '\\r' => "\\\\r",
                    '\\t' => "\\\\t", '\\v' => "\\\\v",
                    '\\e' => "\\\\e"
                ]),
                $item->get('method')
            );

            if ($item->get('alias') != null) {
                $content[] = sprintf("\t\$route->setName('%s');\n", $item->get('alias'));
            }

            if ($item->get('middlewares') != null) {
                foreach ($item->get('middlewares') as $middleware) {
                    $content[] = sprintf("\t\$route->add(new \\%s());", $middleware);
                }
            }
        }

        file_put_contents($path, str_replace('  {{ content }}', implode('', $content), $template));

        return $this;
    }

    public function include()
    {
        return (require_once($this->path))($this->app);
    }
}
