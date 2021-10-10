<?php


namespace Itx\Container;

use Closure;
use Psr\Container\ContainerInterface;
use Itx\Container\Exceptions\{ContainerException, EmptyArgs, NotFoundException};

class Container implements ContainerInterface
{
	private $cache = [];
	private $config = [];
	private $latestClass = null;

	public function __construct($config = [])
	{
		$this->config = $config;

		$this->config["setter"] = $config["setter"] ?? '__onConstruct';

		if ($config["aliases"] ?? false) foreach ($config["aliases"] as $alias => $class) {
			class_alias($class, $alias);
		}

		$this->cacheClass(self::class, $this);
	}


	public function cacheClass($key, &$value)
	{
		$this->cache[$key] = &$value;
	}


	public function set($id, $value)
	{
		$this->cache[$id] = $value;
	}


	public function get($id)
	{
		if (!isset($this->cache[$id])) throw new NotFoundException();

		return $this->cache[$id] ?? null;
	}

	public function has($id)
	{
		return $this->cache[$id] ?? false;
	}

	public function load($class, $args = [], $parent = true)
	{
		$className = $class;

		$class = $this->config[$class] ?? $class;

		if ($class instanceof Closure) {
			return $this->cache[$className] = $class();
		}

		if (gettype($class) == "object") {
			return $this->cache[$className] = $class;
		}


		if (!isset($this->cache[$class])) {

			$funParam = $args;
			try {
				$reflection = new \ReflectionClass($class);

				$constructor = $reflection->getConstructor();

				$constructor &&	$funParam = $this->getFuncParams($constructor, $args);

				$this->cache[$class] =  $reflection->newInstanceArgs($funParam);

				$reflection->hasMethod($this->config["setter"]) && $this->call($this->cache[$class], $this->config["setter"], [], true);
			} catch (\ReflectionException $e) {
				
				$trace = $e->getTrace();

				if($e->getCode() != -1) {
					throw $e ;
				}
				
				throw new \Itx\Container\Exceptions\NotFoundException(
					"{$class} Not found , May be called from {$trace[2]["args"][0]->class}"
				);

			}
		}



		return 	$this->cache[$class];
	}

	public function call($class, $method, $args = [])
	{
		$reflection = new \ReflectionMethod($class, $method);
		$reflection->setAccessible(true);
		$funParam = $this->getFuncParams($reflection, $args);
		return $reflection->invokeArgs($class, $funParam);
	}

	private function getFuncParams($constructor, $replace = [])
	{
		$funParam = [];

		$parameters = $constructor->getParameters();

		$n = 0 ;
		foreach ($parameters as $param) {
			

			if ($param->getType() !== null && !$param->getType()->isBuiltin()) {
				$object = $param->getType()->getName();
				$funParam[] = $this->load($object, [], false);
			} else {
				if($param->isOptional()) {
					$funParam[] = $param->getDefaultValue();
				} else if( isset($replace[$n]) ) {
					$funParam[] = $replace[$n] ;
				} else if( isset($replace[$param->getName()]) ) {
					$funParam[] = $replace[$param->getName()] ;
				} else {
					$message = "%s::%s param %s has no default value and not assigned with value " ;

					throw new \Itx\Container\Exceptions\EmptyArgsException(
						sprintf($message , $constructor->getDeclaringClass()->getName() , $constructor->getName() , $param->getName())
					);
				}
			}
			$n++ ;
		}



		return $funParam;
	}

	public function dump()
	{
		return ($this->cache);
	}

	public function __call($func, $args)
	{
	}
}
