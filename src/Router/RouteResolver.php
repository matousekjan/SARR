<?php
declare(strict_types=1);

namespace Matousekjan\Sarr\Router;

use Matousekjan\Sarr\Exceptions\Error400Exception;
use Matousekjan\Sarr\Exceptions\Error404Exception;
use Matousekjan\Sarr\Exceptions\HttpException;
use Matousekjan\Sarr\Exceptions\ServiceErrorException;
use Matousekjan\Sarr\Mapper\Attributes\Map;
use Matousekjan\Sarr\Responses\ServiceResponse;
use Matousekjan\Sarr\Router\Attributes\HttpRouteAttribute;
use Matousekjan\Sarr\Security\Attributes\Authenticated;
use Matousekjan\Sarr\Security\IAuthenticator;
use Exception;
use Nette\DI\Container;
use ReflectionMethod;
use ReflectionObject;

final class RouteResolver
{
	private Container $diContainer;
	private string $servicesDirectory;
	
	public function __construct(string $tempDirectory, string $configDirectory, string $servicesDirectory, bool $developmentMode = false)
	{
		$loader = new \Nette\DI\ContainerLoader($tempDirectory . '/di', $developmentMode);
		$containerDefinition = $loader->load(function ($compiler) use ($configDirectory) {
			if(file_exists($configDirectory . '/environment.neon'))
			{
				$compiler->loadConfig($configDirectory . '/environment.neon');
			}
			$compiler->loadConfig($configDirectory . '/services.neon');
		});

		$container = new $containerDefinition;
		$this->diContainer = $container;
		$this->servicesDirectory = $servicesDirectory;
	}
	
	public function Resolve()
	{
		try
		{
			$endpointResponse = $this->GetServiceResponse();
			
			header('Content-Type: application/json; charset=utf-8');
			http_response_code(200);
			echo json_encode(new ServiceResponse([], $endpointResponse));
			exit;
		}
		catch(HttpException $exception)
		{
			header('Content-Type: application/json; charset=utf-8');
			http_response_code($exception->GetErrorCode());
			echo json_encode(new ServiceResponse([ $exception->GetErrorCode() ]));
			exit;
		}
		catch(ServiceErrorException $exception)
		{
			header('Content-Type: application/json; charset=utf-8');
			http_response_code(200);
			echo json_encode(new ServiceResponse($exception->GetErrorCodes()));
			exit;
		}
		catch(Exception $e)
		{
			//TODO: Log unhandled exceptions
			header('Content-Type: application/json; charset=utf-8');
			http_response_code(500);

			echo json_encode([
				$e->getMessage(),
				$e->getFile(),
				$e->getLine(),
				$e->getTraceAsString()
			]);
			exit;
		}
	}

	/**
	 * TODO: Cache this list in production mode
	 * TODO: Recursive scan for subdirectories
	 */
	private function GetServices() : array
	{
		$serviceClasses = [];
		foreach(scandir($this->servicesDirectory) as $class)
		{
			if($class === '.' || $class === '..')
				continue;
			
			$class = '\\App\\Services\\' . str_replace('.php', '', $class);
			$serviceClasses[] = $class;
		}

		return $serviceClasses;
	}

	/**
	 * TODO: Refactor heavily
	 */
	private function GetServiceResponse()
	{
		$route = explode('?', $_SERVER['REQUEST_URI'])[0];
		$httpMethod = $_SERVER['REQUEST_METHOD'];
		$services = $this->GetServices();

		$foundReflectionMethod = null;
		$foundServiceObject = null;

		foreach($services as $service)
		{
			$serviceObject = $this->diContainer->getByType($service);
			$reflectionObject = new ReflectionObject($serviceObject);

			foreach($reflectionObject->getMethods(ReflectionMethod::IS_PUBLIC) as $method)
			{
				$routeAttribute = $this->FindHttpRouteAttributeAttribute($method);
				if($routeAttribute === null)
				{
					continue;
				}

				if(!$routeAttribute->MatchRoute($httpMethod, $route))
				{
					continue;
				}
				
				$authenticatedAttribute = $this->FindAttribute($method, Authenticated::class);
				if($authenticatedAttribute != null)
				{
					$this->diContainer->getByType(IAuthenticator::class)->RequireAuth($authenticatedAttribute->Roles);
				}

				$foundReflectionMethod = $method;
				$foundServiceObject = $serviceObject;
				break;
			}

			if($foundReflectionMethod !== null)
			{
				break;
			}
		}

		if($foundReflectionMethod === null)
		{
			throw new Error404Exception();
		}

		$parameters = [];
		foreach($foundReflectionMethod->getParameters() as $parameter)
		{
			$argument = null;

			//TODO: Add parameter type checking
			if(array_key_exists($parameter->getName(), ($httpMethod === 'GET' ? $_GET : $_POST)))
			{
				$argument = $httpMethod === 'GET'
				? $_GET[$parameter->getName()]
				: $_POST[$parameter->getName()];
			}
			else if($parameter->isDefaultValueAvailable())
			{
				$argument = $parameter->getDefaultValue();
			}

			if($argument === null && !($parameter->allowsNull()))
			{
				throw new Error400Exception();
			}

			$parameters[] = $argument;
		}

		$result = $foundReflectionMethod->invokeArgs($foundServiceObject, $parameters);

		$mapAttribute = $this->FindAttribute($foundReflectionMethod, Map::class);
		if($mapAttribute != null)
		{
			$result = $mapAttribute->Map($result);
		}	

		return $result;
	}

	private function FindHttpRouteAttributeAttribute(ReflectionMethod $method) : ?HttpRouteAttribute
	{
		foreach($method->getAttributes() as $attribute)
		{
			$attrInstance = $attribute->newInstance();
			if(is_subclass_of($attrInstance, HttpRouteAttribute::class))
			{
				return $attrInstance;
			}
		}

		return null;
	}

	private function FindAttribute(ReflectionMethod $method, string $attributeType) : ?object
	{
		foreach($method->getAttributes() as $attribute)
		{
			$attrInstance = $attribute->newInstance();
			if(get_class($attrInstance) == $attributeType)
			{
				return $attrInstance;
			}
		}

		return null;
	}
}
