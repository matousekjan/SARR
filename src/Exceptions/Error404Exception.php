<?php
declare(strict_types=1);

namespace Matousekjan\Sarr\Exceptions;

class Error404Exception extends HttpException
{
	protected int $ErrorCode = 404;
}