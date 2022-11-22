<?php
declare(strict_types=1);

namespace Matousekjan\Sarr\Security\Attributes;
use Attribute;

#[Attribute]
final class Authenticated
{
	public array $Roles;

	public function __construct(array|int|object $roles = [])
	{
		if(gettype($roles) != 'array')
		{
			$this->Roles = [ $this->RoleToInt($roles) ];
		}
		else
		{
			$this->Roles = array_map([$this, 'RoleToInt'], $roles);
		}
	}

	private function RoleToInt(object|int $role) : int
	{
		return gettype($role) == "object" ? $role->value : $role;
	}
}