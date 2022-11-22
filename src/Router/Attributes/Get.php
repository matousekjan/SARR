<?php
declare(strict_types=1);

namespace Matousekjan\Sarr\Router\Attributes;

use Matousekjan\Sarr\Router\HttpMethod;
use Attribute;

#[Attribute]
final class Get extends HttpRouteAttribute
{
	public function __construct(string $route)
	{
		parent::__construct(HttpMethod::GET, $route);
	}
}