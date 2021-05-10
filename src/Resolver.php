<?php

declare(strict_types=1);

namespace Sellony\Route;

use Psr\Http\Message\{
    ResponseInterface,
    ServerRequestInterface
};

use Slim\Interfaces\InvocationStrategyInterface;
use Illuminate\Support\Arr;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionClass;
use ReflectionParameter;
use Closure;

use function array_values;

/**
 * Route callback strategy with route parameters as individual arguments.
 */
class Resolver implements InvocationStrategyInterface
{
    private $container;
    private $request;
    private $response;

    /**
     * Invoke a route callable with request, response and all route parameters
     * as individual arguments.
     *
     * @param callable               $callable
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @param array                  $routeArguments
     *
     * @return ResponseInterface
     */
    public function __invoke(
        callable $callable,
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $routeArguments
    ): ResponseInterface {
        $this->container = container();
        $this->request = $request;
        $this->response = $response;

        if ($callable instanceof Closure) {
            return $callable($request, $response, ...array_values($routeArguments));
        }

        $controller = $callable[0];
        $method = $callable[1];

        $parameters = $this->resolveClassMethodDependencies(
            [],
            $controller,
            $method
        );

        $parameters += $routeArguments;

        # cast 'x' to integer
        foreach ($parameters as $key => $param) {
            if (is_numeric($param)) {
                $parameters[$key] = (int) $param;
            }
        }

        try {
            return $controller->{$method}(...array_values($parameters));
        } catch (\ArgumentCountError $e) {
            $parameters[count($parameters)] = null;
            return $controller->{$method}(...array_values($parameters));
        }
    }

    /**
     * Resolve the object method's type-hinted dependencies.
     *
     * @param  array  $parameters
     * @param  object  $instance
     * @param  string  $method
     * @return array
     */
    protected function resolveClassMethodDependencies(array $parameters, $instance, $method)
    {
        if (! method_exists($instance, $method)) {
            return $parameters;
        }

        return $this->resolveMethodDependencies(
            $parameters,
            new ReflectionMethod($instance, $method)
        );
    }

    /**
     * Resolve the given method's type-hinted dependencies.
     *
     * @param  array  $parameters
     * @param  \ReflectionFunctionAbstract  $reflector
     * @return array
     */
    public function resolveMethodDependencies(array $parameters, ReflectionFunctionAbstract $reflector)
    {
        $instanceCount = 0;

        $values = array_values($parameters);

        foreach ($reflector->getParameters() as $key => $parameter) {
            if ($parameter->name == 'request') {
                $parameters[] = $this->request;
                continue;
            }

            if ($parameter->name == 'response') {
                $parameters[] = $this->response;
                continue;
            }

            $instance = $this->transformDependency($parameter, $parameters);

            if (! is_null($instance)) {
                $instanceCount++;
                $this->spliceIntoParameters($parameters, $key, $instance);
            } elseif (!isset($values[$key - $instanceCount]) && $parameter->isDefaultValueAvailable()) {
                $this->spliceIntoParameters($parameters, $key, $parameter->getDefaultValue());
            }
        }


        return $parameters;
    }

    /**
     * Attempt to transform the given parameter into a class instance.
     *
     * @param  \ReflectionParameter  $parameter
     * @param  array  $parameters
     * @return mixed
     */
    protected function transformDependency(ReflectionParameter $parameter, $parameters)
    {
        $class = $parameter->getType() && !$parameter->getType()->isBuiltin()
        ? new ReflectionClass($parameter->getType()->getName())
        : null;

        if ($class && ! $this->alreadyInParameters($class, $parameters)) {
            return $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : $this->container->make($class);
        }
    }

    /**
     * Determine if an object of the given class is in a list of parameters.
     *
     * @param  string  $class
     * @param  array  $parameters
     * @return bool
     */
    protected function alreadyInParameters($class, array $parameters)
    {
        return ! is_null(Arr::first($parameters, function ($value) use ($class) {
            return $value instanceof $class;
        }));
    }

    /**
     * Splice the given value into the parameter list.
     *
     * @param  array  $parameters
     * @param  string  $offset
     * @param  mixed  $value
     * @return void
     */
    protected function spliceIntoParameters(array &$parameters, $offset, $value)
    {
        array_splice($parameters, $offset, 0, [$value]);
    }
}
