<?php
declare(strict_types=1);

namespace Matousekjan\Sarr\Security;

/**
 * Object providing features for user authentication
 */
interface IAuthenticator
{
	/**
	 * @param array $roles Roles required for successful authentication
	 * 
	 * @throws \Matousekjan\Sarr\Exceptions\Error401Exception
	 * @return bool True if user is authenticated, false is never returned. If false should be returned, the exception is thrown 
	 */
	public function RequireAuth(array $roles = []) : bool;

	/**
	 * @param int 	$userId Unique identificator of the user
	 * @param array $roles 	Roles associated with the user
	 * 
	 * @return string Encrypted JWT token
	 */
    public function GenerateToken(int $userId, array $roles = []) : string;

	/**
	 * @return ?int Identificator of the user. Null if user is not logged in.
	 */
	public function GetUserId() : ?int;

	/**
	 * @return bool True if user is logged in
	 */
	public function IsUserLoggedIn() : bool;

	/**
	 * @return ?array Array of roles. If the user is not logged in, null is returned
	 */
	public function GetUserRoles() : ?array;
}