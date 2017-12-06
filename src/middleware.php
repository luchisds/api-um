<?php
// Application middleware

use Slim\Http\Request;
use Slim\Http\Response;

//use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Parser;
use Lcobucci\JWT\ValidationData;
use Lcobucci\JWT\Signer\Hmac\Sha256;

// e.g: $app->add(new \Slim\Csrf\Guard);
class JWTMiddleware {

  public function __invoke(Request $request, Response $response, callable $next) {
    global $secretServerKey;
    $tokenAuthentication = $request->getHeader('token-auth');

    if(empty($tokenAuthentication) || strlen($tokenAuthentication[0]) === 0) {
      return $response->withJson(array('status' => 'error', 'description' => 'Cabecera de token no válida'));
    }

    $signer = new Sha256();
    $token = (new Parser())->parse((string) $tokenAuthentication[0]); // Parses from a string
    if($token->verify($signer, $secretServerKey) !== true) {
      return $response->withJson(array('status' => 'error', 'description' => 'Signature no válido'));
    }

    $dataTokenValidation = new ValidationData();
    $dataTokenValidation->setCurrentTime(time());
    if($token->validate($dataTokenValidation) !== true) {
      return $response->withJson(array('status' => 'error', 'description' => 'Token vencido. Vuelva a loguearse.'));
    }

    return $next($request, $response);
  }
  
}
