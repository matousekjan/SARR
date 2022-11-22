<?php
declare(strict_types=1);

namespace Matousekjan\Sarr\Exceptions;

use Exception;

abstract class HttpException extends Exception
{
	protected int $ErrorCode;
	
	public function GetErrorCode() : int
	{
		return $this->ErrorCode;
	}
}