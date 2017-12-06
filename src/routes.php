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
    if($validation['isValid'] === true) {
      $product = Product::where('product_id', $toClean->product_id)->first();
      if($product === null) {
        $messages = array('status' => 'error', 'description' => 'Código de producto inexistente');
      } else {
        $product->price = $toClean->price;
        $product->stock = $toClean->stock;
        $product->save();
        $messages = array('status' => 'ok', 'description' => 'Actualización correcta');
      }
    } else {
      $messages = array('status' => 'error', 'description' => $validation['errors']);
    }

    return $response->withJson($messages);
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
    if($validation['isValid'] === true) {
      if(Product::where('product_id', $toClean->product_id)->first() === null) {
        $messages = array('status' => 'error', 'description' => 'Código de producto inexistente');
      } elseif(Purchase::where('product_id', $toClean->product_id)->where('invoice', $toClean->invoice)->first() !== null) {
        $messages = array('status' => 'error', 'description' => 'Compra ya registrada anteriormente');
      } else {
        $purchase = new Purchase;
        $purchase->product_id = $toClean->product_id;
        $purchase->quantity = $toClean->quantity;
        $purchase->date = $toClean->date;
        $purchase->invoice = $toClean->invoice;
        $purchase->save();
        $messages = array('status' => 'ok', 'description' => 'Actualización correcta');
      }
    } else {
      $messages = array('status' => 'error', 'description' => $validation['errors']);
    }

    return $response->withJson($messages);
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
      if($validation['isValid'] === true) {
        $purchase = Purchase::whereBetween('date', [$toClean->fromDate, $toClean->toDate])->get();
        $messages = array('status' => 'ok', 'data' => $purchase);
      } else {
        $messages = array('status' => 'error', 'description' => $validation['errors']);
      }
    } else {
      if(isset($data['fromDate'])) {
        $messages = array('status' => 'error', 'description' => 'Falta fecha hasta (toDate)');
      } elseif(isset($data['toDate'])) {
        $messages = array('status' => 'error', 'description' => 'Falta fecha desde (fromDate)');
      } else {
        $purchase = Purchase::all();
        $messages = array('status' => 'ok', 'data' => $purchase);
      }
    }

    return $response->withJson($messages);
  });
})->add(new JWTMiddleware());

// Login to the API
$app->post('/login', function(Request $request, Response $response, array $args) {
  global $secretServerKey;

  $headerAuthentication = $request->getHeader('authentication');
  $headerTimestamp = $request->getHeader('timestamp');

  if(empty($headerAuthentication) || empty($headerTimestamp)) {
    $messages = array('status' => 'error', 'description' => 'Cabeceras no encontradas');
  } else {
    if(time() - $headerTimestamp[0] > 300) {
      $messages = array('status' => 'error', 'description' => 'El request supera el tiempo limite de operación')
    } else {
      if(strpos(strtoupper($headerAuthentication[0]), 'HMAC ') === false) {
        $messages = array('status' => 'error', 'description' => 'Cabecera de autenticación no hallada');
      } else {
        $authKeySig = substr($headerAuthentication[0], 5);
        if(count(explode(':', $authKeySig)) !== 2) {
          $messages = array('status' => 'error', 'description' => 'La cabecera de autenticacion no es válida');
        } else {
          list($publicKey, $hmacSignature) = explode(':', $authKeySig);

          $apiKey = ApiKey::where('public_key', $publicKey)->first();
          if($apiKey->secret_key === null) {
            $messages = array('status' => 'error', 'description' => 'Clave pública no válida');
          } else {
            $uri = $request->getUri();

            $payload = $request->getMethod() . '&';
            $payload .= $uri->getPath() . '&';
            $payload .= $headerTimestamp[0];

            $hash = hash_hmac('sha256', $payload, $apiKey->secret_key, false);

            if($hmacSignature === $hash) {
              $signer = new Sha256();
              $token = (new Builder())->setIssuedAt(time()) // The time that the token was issue
                                      ->setExpiration(time() + 300) // Set the expiration time of the token in 1 hs. (3600)
                                      ->sign($signer, $secretServerKey) // Signature using "testing" as key
                                      ->getToken();

              $messages = array('status' => 'ok', 'token' => ((string) $token));
            } else {
              $messages = array('status' => 'error', 'description' => 'Signature no válido');
            }
          }
        }
      }
    }
  }

  return $response->withJson($messages);
});
