<?php
declare(strict_types=1);

namespace Matousekjan\Sarr\Mapper\Attributes;

use Matousekjan\Sarr\Mapper\DtoMapper;
use Attribute;

#[Attribute]
final class Map
{
	private string $Type;

	public function __construct(string $type)
	{
		$this->Type = $type;
	}

	public function Map(object|array $objectToMap)
	{
		return DtoMapper::Map($objectToMap, $this->Type);
	}
}