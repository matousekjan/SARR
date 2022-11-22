<?php
declare(strict_types=1);

namespace Matousekjan\Sarr\Mapper;

use ReflectionObject;
use ReflectionProperty;

/**
 * Mapper for converting objects to DTOs
 */
class DtoMapper
{
	public static function Map(object | array $convertedObjects, string $targetType) : object | array
	{
		if(is_array($convertedObjects))
		{
			$array = [];
			foreach($convertedObjects as $convertedObject)
			{
				$array[] = self::mapObject($convertedObject, $targetType);
			}

			return $array;
		}
		else
		{
			return self::mapObject($convertedObjects, $targetType);
		}
	}

	private static function mapObject(object $convertedObject, $targetType) : object
	{
		$newObject = new $targetType;
		$reflectionObject = new ReflectionObject($newObject);
		foreach($reflectionObject->getProperties(ReflectionProperty::IS_PUBLIC) as $property)
		{
			$propertyName = $property->getName();
			$newObject->{$propertyName} = $convertedObject->{$propertyName};
		}

		return $newObject;
	}
}