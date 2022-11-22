<?php
declare(strict_types=1);

namespace Matousekjan\Sarr\Security;

use Matousekjan\Sarr\Exceptions\Error401Exception;
use Matousekjan\Sarr\Security\IAuthenticator;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use stdClass;

/**
 * JWT User Authenticator providing features for user JWT authentication
 */
final class JWTAuthenticator implements IAuthenticator
{
    private string $secret;
    private string $issuer;
    private string $audience;
	private ?stdClass $parsedToken = null;

    public function __construct(string $secret, string $issuer, string $audience)
    {
        $this->secret = $secret;
        $this->issuer = $issuer;
        $this->audience = $audience;
    }

    public function RequireAuth(array $roles = []) : bool
    {
		$token = $this->GetParsedToken($throw = true);
		$userRoles = $this->GetUserRoles();
		foreach($roles as $role)
		{
			if(!in_array($role, $userRoles))
			{
				throw new Error401Exception();
			}
		}

		return true;
    }

	public function IsUserLoggedIn() : bool
	{
		return $this->GetParsedToken($throw = false) != null;
	}

	public function GetUserId() : ?int
	{
		return $this->GetParsedToken($throw = false)?->userId;
	}

	public function GetUserRoles() : ?array
	{
		$rolesString = $this->GetParsedToken($throw = false)?->roles;
		if($rolesString == null)
		{
			return null;
		}

		$rolesString = substr($rolesString, 1, strlen($rolesString) - 2);
		$strArr = explode(";", $rolesString);

		$roleArr = [];
		foreach($strArr as $strRole)
		{
			$roleArr[] = intval($strRole);
		}

		return $roleArr;
	}

    public function GenerateToken(int $userId, array $roles = [], int $expirationInMinutes = 30) : string
    {
        $date = new \DateTimeImmutable();
        $expire = $date->modify("+{$expirationInMinutes} minutes")->getTimestamp();

		$roles = array_map([$this, 'RoleToInt'], $roles);

        $jwtData = [
            "iss" => $this->issuer,
            "aud" => $this->audience,
            "iat" => $date->getTimestamp(),
            "nbf" => $date->getTimestamp(),
            "exp" => $expire,
            "userId" => $userId,
			"roles" => ";" . implode(";", array_map('intval', $roles)) . ";"
        ];

        $token = JWT::encode($jwtData, $this->secret, "HS512");
        return $token;
    }
	

	private function RoleToInt(object|int $role) : int
	{
		return gettype($role) == "object" ? $role->value : $role;
	}

	private function GetParsedToken(bool $throw = true) : ?stdClass
	{
		if($this->parsedToken != null)
		{
			return $this->parsedToken;
		}

        if (!preg_match('/Bearer\s(\S+)/', $_SERVER["REDIRECT_HTTP_AUTHORIZATION"], $matches))
        {
			if($throw) throw new Error401Exception();
			return null;
        }

        $jwt = $matches[1];
        if (!$jwt)
        {
			if($throw) throw new Error401Exception();
			return null;
        }

        $token = $this->ParseToken($jwt);
        
        if($token === null)
        {
			if($throw) throw new Error401Exception();
			return null;
        }

		$this->parsedToken = $token;

		return $this->parsedToken;
	}

    private function ParseToken(string $parsedToken) : ?stdClass
    {
        $date = new \DateTimeImmutable();
        $token = null;

        try
        {
            $token = JWT::decode($parsedToken, new Key($this->secret, "HS512"));
        }
        catch(\Exception)
        {
            return null;
        }

        if($token->iss !== $this->issuer || $token->nbf > $date->getTimestamp() || $token->exp < $date->getTimestamp())
        {
            return null;
        }

        return $token;
    }
}
