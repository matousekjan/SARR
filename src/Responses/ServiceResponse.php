<?php
declare(strict_types=1);

namespace Matousekjan\Sarr\Responses;

/**
 * Base class for responses
 */
class ServiceResponse
{
    public array $errors = [];
	public null|object|string|int|bool|array $response = null;

	public function __construct(array $errors = [], null|object|string|int|bool|array $response = null)
	{
		$this->errors = $errors;
		$this->response = $response;
	}
}
