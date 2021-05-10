<?php


namespace Sellony\Route\Annotation;

class RouteModel
{
    /**
     * @var string
     */
    private $verb;

    /**
     * @var string
     */
    private $route;

    /**
     * @var string
     */
    private $className;

    /**
     * @var string
     */
    private $methodName;

    /**
     * @var string
     */
    private $alias;

    /**
     * @var array
     */
    private $classMiddleware;

    /**
     * RouteModel constructor.
     * @param $verb
     * @param $route
     * @param $className
     * @param $methodName
     * @param null $alias
     * @param array $classMiddleware
     *
     */
    public function __construct($verb, $route, $className, $methodName, $alias = null, $classMiddleware = [])
    {
        $this->verb = $verb;
        $this->route = $route;
        $this->className = $className;
        $this->methodName = $methodName;
        $this->alias = $alias;
        $this->classMiddleware = $classMiddleware;
    }

    /**
     * @return string
     */
    public function getVerb()
    {
        return $this->verb;
    }

    /**
     * @param string $verb
     */
    public function setVerb(string $verb)
    {
        $this->verb = $verb;
    }

    /**
     * @return string
     */
    public function getRoute()
    {
        return $this->route;
    }

    /**
     * @param string $route
     */
    public function setRoute(string $route)
    {
        $this->route = $route;
    }

    /**
     * @return string
     */
    public function getClassName()
    {
        return $this->className;
    }

    /**
     * @param string $className
     */
    public function setClassName(string $className)
    {
        $this->className = $className;
    }

    /**
     * @return string
     */
    public function getMethodName()
    {
        return $this->methodName;
    }

    /**
     * @param string $methodName
     */
    public function setMethodName(string $methodName)
    {
        $this->methodName = $methodName;
    }

    /**
     * @return string
     */
    public function getAlias()
    {
        return $this->alias;
    }

    /**
     * @param string $alias
     */
    public function setAlias(string $alias)
    {
        $this->alias = $alias;
    }

    /**
     * @return array
     */
    public function getClassMiddleware() : array
    {
        return $this->classMiddleware;
    }

    /**
     * @param array $classMiddleware
     */
    public function setClassMiddleware(array $classMiddleware)
    {
        $this->classMiddleware = $classMiddleware;
    }

    public function __toArray()
    {
        $arrayReturn = [
            $this->verb,
            $this->route,
            $this->className,
            $this->methodName,
            ];

        if (!is_null($this->alias)) {
            $arrayReturn[] = $this->alias;
        }

        return $arrayReturn;
    }
}