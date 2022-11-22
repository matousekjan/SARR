<?php
declare(strict_types=1);

namespace Matousekjan\Sarr\Router;

/**
 * List of HTTPS methods
 */
enum HttpMethod : string
{
    case GET = 'GET';
    case POST = 'POST';

	public function toString(): string {
		return match($this){
		  self::GET => 'GET',
		  self::POST => 'POST',
		  default => '',
		};
	  }
}
