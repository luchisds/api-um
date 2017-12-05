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
    $tokenAuthentication = $request->getHeader('token-auth');

    if(empty($tokenAuthentication) || strlen($tokenAuthentication[0]) === 0) {
      //return $response->withStatus(401);
      return $response->withJson(array('error' => 'Cabecera de token no válida'));
    } else {
      $token = (new Parser())->parse((string) $tokenAuthentication[0]); // Parses from a string
      $signer = new Sha256();
      if($token->verify($signer, 'testing') !== true) {
        return $response->withJson(array('error' => 'Signature no válido'));
      } else {
        $dataTokenValidation = new ValidationData();
        $dataTokenValidation->setCurrentTime(time());
        if($token->validate($dataTokenValidation) !== true) {
          return $response->withJson(array('error' => 'Token vencido. Vuelva a loguearse.'));
        } else {
          return $next($request, $response);
        }
      }
    }
  }

}
