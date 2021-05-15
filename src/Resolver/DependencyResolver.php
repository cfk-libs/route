<?php

declare(strict_types=1);

namespace Sellony\Route\Resolver;

use Psr\Http\Message\{
    RequestInterface,
    ResponseInterface,
    ServerRequestInterface
};

use Psr\Container\ContainerInterface;
use Slim\Interfaces\InvocationStrategyInterface;
use ReflectionMethod;
use ReflectionClass;
use ReflectionFunction;
use ReflectionParameter;
use Closure;
use ArgumentCountError;

use function array_values;

/**
 * Route callback strategy with route parameters as individual arguments.
 */
class DependencyResolver implements InvocationStrategyInterface
{
    public function __construct(public ContainerInterface $container){}

    /**
     * Invoke a route callable with request, response, and all route parameters
     * as an array of arguments.
     *
     * @param callable               $callable
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @param array<mixed>           $routeArguments
     *
     * @return ResponseInterface
     */
    public function __invoke(
        callable $callable,
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $routeArguments
    ): ResponseInterface {
        foreach ($routeArguments as $k => $v) {
            $request = $request->withAttribute($k, $v);
        }

        $this->container->singleton(RequestInterface::class, function() use ($request) {
            return $request;
        });

        $this->container->singleton(ResponseInterface::class, function() use ($response) {
            return $response;
        });

        $function = $callable instanceof Closure ?
            new ReflectionFunction($callable) :
            new ReflectionMethod($callable[0], $callable[1]);

        $parameters = [];
        foreach($function->getParameters() as $key => $parameter) {
            $dependency = $this->transformDependency($parameter);

            if (is_null($dependency) && $argument = data_get($routeArguments, $parameter->getName())) {
                $dependency = is_numeric($argument) ? (int) $argument : $argument;
            }

            $parameters[] = $dependency;
        }

        if ($callable instanceof Closure) {
            return $callable(...array_values($parameters));
        }

        return $callable[0]->{$callable[1]}(...array_values($parameters));
    }

    /**
     * Attempt to transform the given parameter into a class instance.
     *
     * @param  ReflectionParameter  $parameter
     * @return mixed
     */
    protected function transformDependency(ReflectionParameter $parameter)
    {
        if (!$parameter->getType() || $parameter->getType()->isBuiltin()) {
            return $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null;
        }

        $class = new ReflectionClass($parameter->getType()->getName());

        return $this->container->make($class->getName());
    }
}
