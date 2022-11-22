<?php
declare(strict_types=1);

namespace Matousekjan\Sarr\Exceptions;

use Exception;

class ServiceErrorException extends Exception
{
	protected array $ErrorCodes;

	public function __construct(array|int|object $errors)
	{
		if(gettype($errors) != 'array')
		{
			$this->ErrorCodes = [ $this->ErrorToInt($errors) ];
		}
		else
		{
			$this->ErrorCodes = array_map([$this, 'ErrorToInt'], $errors);
		}
	}

	public function GetErrorCodes() : array
	{
		return $this->ErrorCodes;
	}

	private function ErrorToInt(object|int $role) : int
	{
		return gettype($role) == "object" ? $role->value : $role;
	}
}