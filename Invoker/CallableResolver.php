<?php

/**
 * @package Sellony Api | Channel::Admin
 * @author Cemre Fatih Karakulak <cradexco@gmail.com>
 */

namespace Sellony\Router\Invoker;

use Slim\Interfaces\CallableResolverInterface;
use Sellony\Router\Invoker\CallableResolverInvoker;

/**
 * Resolve middleware and route callables using PHP-DI.
 */
class CallableResolver implements CallableResolverInterface
{
    /**
     * @var \Invoker\CallableResolver
     */
    private $callableResolver;

    public function __construct(CallableResolverInvoker $callableResolver)
    {
        $this->callableResolver = $callableResolver;
    }
    /**
     * {@inheritdoc}
     */
    public function resolve($toResolve): callable
    {
        return $this->callableResolver->resolve($toResolve);
    }
}
