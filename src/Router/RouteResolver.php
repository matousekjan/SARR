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
use Nette\DI\MissingServiceException;
use ReflectionMethod;
use ReflectionObject;

final class RouteResolver
{
	private Container $diContainer;
	private string $servicesDirectory;
	private string $diDirectoryName = 'di';
	private string $environmentConfigFileName = 'environment.neon';
	private string $servicesConfigFileName = 'services.neon';
	
	/**
	 * Creates instance of the RouteResolver
	 * 
	 * @param string 	$tempDirectory		Location of the temp directory
	 * @param string 	$configDirectory	Location of the config directory
	 * @param string 	$servicesDirectory	Location of the directory containing services
	 * @param bool 		$developmentMode	If true, the SARR will run in development mode (caching turned off)
	 * @param bool 		$autoResolve		If true, Resolve() method is called automatically
	 */
	public function __construct(string $tempDirectory, string $configDirectory, string $servicesDirectory, bool $developmentMode = false, bool $autoResolve = true)
	{
		ini_set('html_errors', false);

		$loader = new \Nette\DI\ContainerLoader($tempDirectory . '/' . $this->diDirectoryName, $developmentMode);
		$containerDefinition = $loader->load(function ($compiler) use ($configDirectory) {
			if(file_exists($configDirectory . '/' . $this->environmentConfigFileName))
			{
				$compiler->loadConfig($configDirectory . '/' . $this->environmentConfigFileName);
			}
			$compiler->loadConfig($configDirectory . '/' . $this->servicesConfigFileName);
		});

		$container = new $containerDefinition;
		$this->diContainer = $container;
		$this->servicesDirectory = $servicesDirectory;

		if($autoResolve)
		{
			$this->Resolve();
		}
	}
	
	/**
	 * Calling this method will resolve the request.
	 * There is no need to call this method unless you set $autoResolve = false in the constructor.
	 */
	public function Resolve()
	{
		header('Content-Type: application/json; charset=utf-8');
		try
		{
			$endpointResponse = $this->GetServiceResponse();
			
			http_response_code(200);
			echo json_encode(new ServiceResponse([], $endpointResponse));
		}
		catch(HttpException $exception)
		{
			http_response_code($exception->GetErrorCode());
			echo json_encode(new ServiceResponse([ $exception->GetErrorCode() ]));
		}
		catch(ServiceErrorException $exception)
		{
			http_response_code(200);
			echo json_encode(new ServiceResponse($exception->GetErrorCodes()));
		}
		catch(Exception $e)
		{
			//TODO: Log unhandled exceptions
			http_response_code(500);

			echo json_encode([
				$e->getMessage(),
				$e->getFile(),
				$e->getLine(),
				$e->getTraceAsString()
			]);
		}
		finally
		{
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
			$serviceObject = null;
			try
			{
				$serviceObject = $this->diContainer->getByType($service);
			}
			catch(MissingServiceException)
			{
				continue;
			}
			
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

			$requiredType = $parameter->getType();

			$argumentRealValue = null;
			if($parameter->hasType() && !$this->CheckParameterType($argument, $requiredType, $parameter->allowsNull(), $argumentRealValue))
			{
				throw new Error400Exception();
			}

			$parameters[] = $argumentRealValue;
		}

		$result = $foundReflectionMethod->invokeArgs($foundServiceObject, $parameters);

		$mapAttribute = $this->FindAttribute($foundReflectionMethod, Map::class);
		if($mapAttribute != null)
		{
			$result = $mapAttribute->Map($result);
		}	

		return $result;
	}

	private function CheckParameterType($argumentValue, $requiredType, $nullAllowed, &$outRealValue)
	{
		$attrRealType = gettype($argumentValue);
		if($attrRealType == $requiredType)
		{
			$outRealValue = $argumentValue;
			return true;
		}

		if($nullAllowed && $argumentValue === null)
		{
			$outRealValue = null;
			return true;
		}

		if($nullAllowed && $requiredType != "string" && ($argumentValue === "null" || $argumentValue === "NULL"))
		{
			$outRealValue = null;
			return true;
		}

		switch($requiredType)
		{
			case "?string":
			case "string":
				$outRealValue = strval($argumentValue);
				return true;

			case "?int":
			case "int":
				if($attrRealType != "string" || !is_numeric($argumentValue))
				{
					$outRealValue = null;
					return false;
				}
				$floatVal = floatval($argumentValue);
				$intVal = intval($argumentValue);
				if($floatVal != $intVal)
				{
					$outRealValue = null;
					return false;
				}

				$outRealValue = $intVal;
				return true;
		
			case "?float":
			case "float":
				if($attrRealType != "string" || !is_numeric($argumentValue))
				{
					$outRealValue = null;
					return false;
				}
				$floatVal = floatval($argumentValue);

				$outRealValue = $floatVal;
				return true;

			case "?bool":
			case "bool":
				if($argumentValue !== "true" && $argumentValue !== "false" && $argumentValue !== "TRUE" && $argumentValue !== "FALSE")
				{
					$outRealValue = null;
					return false;
				}
				$outRealValue = $argumentValue === "true" || $argumentValue === "TRUE";
				return true;

			case "?array":
			case "array":
				if($attrRealType != "array")
				{
					$outRealValue = null;
					return false;
				}
				$outRealValue = $argumentValue;
				return true;
		}

		$outRealValue = null;
		return false;
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
