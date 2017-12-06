<?php

use Slim\Http\Request;
use Slim\Http\Response;

use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Signer\Hmac\Sha256;


// Import DB Models
require __DIR__ . '/models/product.php';
require __DIR__ . '/models/purchase.php';
require __DIR__ . '/models/apikey.php';
// Import Validators
require __DIR__ . '/validators.php';

// Routes
$app->group('/v1', function () use($app) {
  // Update price and stock by product color
  $app->post('/productos/{id}', function(Request $request, Response $response, array $args) {
    $data = $request->getParsedBody();

    //validation
    $toClean = new \stdClass();
    $toClean->product_id = $args['id'];
    $toClean->price = $data['price'];
    $toClean->stock = $data['stock'];
    $validation = validateProductUpdate($toClean);

    //actions
    if($validation['isValid'] !== true) {
      return $response->withJson(array('status' => 'error', 'description' => $validation['errors']));
    }

    $product = Product::where('product_id', $toClean->product_id)->first();
    if($product === null) {
      return $response->withJson(array('status' => 'error', 'description' => 'Código de producto inexistente'));
    }

    $product->price = $toClean->price;
    $product->stock = $toClean->stock;
    $product->save();
    return $response->withJson(array('status' => 'ok', 'description' => 'Actualización correcta'));
  });


  // Update purchases by product
  $app->post('/compras/{id}', function(Request $request, Response $response, array $args) {
    $data = $request->getParsedBody();

    //validation
    $toClean = new \stdClass();
    $toClean->product_id = $args['id'];
    $toClean->quantity = $data['quantity'];
    $toClean->date = $data['date'];
    $toClean->invoice = $data['invoice'];
    $validation = validatePurchaseUpdate($toClean);

    //actions
    if($validation['isValid'] !== true) {
      return $response->withJson(array('status' => 'error', 'description' => $validation['errors']));
    }

    if(Product::where('product_id', $toClean->product_id)->first() === null) {
      return $response->withJson(array('status' => 'error', 'description' => 'Código de producto inexistente'));
    } elseif(Purchase::where('product_id', $toClean->product_id)->where('invoice', $toClean->invoice)->first() !== null) {
      return $response->withJson(array('status' => 'error', 'description' => 'Compra ya registrada anteriormente'));
    }

    $purchase = new Purchase;
    $purchase->product_id = $toClean->product_id;
    $purchase->quantity = $toClean->quantity;
    $purchase->date = $toClean->date;
    $purchase->invoice = $toClean->invoice;
    $purchase->save();
    return $response->withJson(array('status' => 'ok', 'description' => 'Actualización correcta'));
  });


  // Check purchases made between two dates
  $app->get('/compras', function(Request $request, Response $response, array $args) {
    $data = $request->getQueryParams();

    if(isset($data['fromDate']) && isset($data['toDate'])) {
      //validation
      $toClean = new \stdClass();
      $toClean->fromDate = $data['fromDate'];
      $toClean->toDate = $data['toDate'];
      $validation = validatePurchaseCheck($toClean);

      //actions
      if($validation['isValid'] !== true) {
        return $response->withJson(array('status' => 'error', 'description' => $validation['errors']));
      }

      $purchase = Purchase::whereBetween('date', [$toClean->fromDate, $toClean->toDate])->get();
      return $response->withJson(array('status' => 'ok', 'data' => $purchase));
    } else {
      if(isset($data['fromDate'])) {
        return $response->withJson(array('status' => 'error', 'description' => 'Falta fecha hasta (toDate)'));
      } elseif(isset($data['toDate'])) {
        return $response->withJson(array('status' => 'error', 'description' => 'Falta fecha desde (fromDate)'));
      }

      $purchase = Purchase::all();
      return $response->withJson(array('status' => 'ok', 'data' => $purchase));
    }
  });
})->add(new JWTMiddleware());

// Login to the API
$app->post('/login', function(Request $request, Response $response, array $args) {
  global $secretServerKey;
  $headerAuthentication = $request->getHeader('authentication');
  $headerTimestamp = $request->getHeader('timestamp');

  if(empty($headerAuthentication) || empty($headerTimestamp)) {
    return $response->withJson(array('status' => 'error', 'description' => 'Cabeceras no encontradas'));
  }

  if(time() - $headerTimestamp[0] > 300) {
    return $response->withJson(array('status' => 'error', 'description' => 'El request supera el tiempo limite de operación'));
  }

  if(strpos(strtoupper($headerAuthentication[0]), 'HMAC ') === false) {
    return $response->withJson(array('status' => 'error', 'description' => 'Cabecera de autenticación no hallada'));
  }

  $authKeySig = substr($headerAuthentication[0], 5);
  if(count(explode(':', $authKeySig)) !== 2) {
    return $response->withJson(array('status' => 'error', 'description' => 'La cabecera de autenticacion no es válida'));
  }

  list($publicKey, $hmacSignature) = explode(':', $authKeySig);
  $apiKey = ApiKey::where('public_key', $publicKey)->first();
  if($apiKey === null) {
    return $response->withJson(array('status' => 'error', 'description' => 'Clave pública no válida'));
  }

  $payload = $request->getMethod() . '&';
  $payload .= $request->getUri()->getPath() . '&';
  $payload .= $headerTimestamp[0];

  $hash = hash_hmac('sha256', $payload, $apiKey->secret_key, false);

  if($hmacSignature !== $hash) {
    return $response->withJson(array('status' => 'error', 'description' => 'Signature no válido'));
  }

  $signer = new Sha256();
  $token = (new Builder())->setIssuedAt(time()) // The time that the token was issue
                          ->setExpiration(time() + 300) // Set the expiration time of the token in 1 hs. (3600)
                          ->sign($signer, $secretServerKey) // Signature using "testing" as key
                          ->getToken();

  return $response->withJson(array('status' => 'ok', 'token' => ((string) $token)));
});
