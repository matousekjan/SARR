<?php
declare(strict_types=1);

namespace Matousekjan\Sarr\Router\Attributes;

use Matousekjan\Sarr\Router\HttpMethod;
use Attribute;

#[Attribute]
class HttpRouteAttribute
{
	protected string $route;
	protected HttpMethod $method;

	public function __construct(HttpMethod $method, string $route)
	{
		$this->route = $route;
		$this->method = $method;
	}

	public function MatchRoute(string $method, string $route)
	{
		return $method === $this->method->toString() && $route == $this->route;
	}
}