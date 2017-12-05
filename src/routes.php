<?php

use Slim\Http\Request;
use Slim\Http\Response;

// Import DB Models
require __DIR__ . '/../src/models/product.php';
require __DIR__ . '/../src/models/purchase.php';
require __DIR__ . '/../src/models/apikey.php';
// Import Validators
require __DIR__ . '/../src/validators.php';


// Routes

// Update price and stock by product color
$app->post('/v1/productos/{id}', function(Request $request, Response $response, array $args) {
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
      $messages = array('error' => 'Código de producto inexistente');
    } else {
      $product->price = $toClean->price;
      $product->stock = $toClean->stock;
      $product->save();
      $messages = array('status' => 'Actualización correcta');
    }
  } else {
    $messages = array('validation' => $validation['errors']);
  }

  return $response->withJson($messages);
});


// Update purchases by product
$app->post('/v1/compras/{id}', function(Request $request, Response $response, array $args) {
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
      $messages = array('error' => 'Código de producto inexistente');
    } elseif(Purchase::where('product_id', $toClean->product_id)->where('invoice', $toClean->invoice)->first() !== null) {
      $messages = array('error' => 'Compra ya registrada anteriormente');
    } else {
      $purchase = new Purchase;
      $purchase->product_id = $toClean->product_id;
      $purchase->quantity = $toClean->quantity;
      $purchase->date = $toClean->date;
      $purchase->invoice = $toClean->invoice;
      $purchase->save();
      $messages = array('status' => 'Actualización correcta');
    }
  } else {
    $messages = array('validation' => $validation['errors']);
  }

  return $response->withJson($messages);
});


// Check purchases made between two dates
$app->get('/v1/compras', function(Request $request, Response $response, array $args) {
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
      $messages = array('validation' => $validation['errors']);
    }
  } else {
    if(isset($data['fromDate'])) {
      $messages = array('error' => 'Falta fecha hasta (toDate)');
    } elseif(isset($data['toDate'])) {
      $messages = array('error' => 'Falta fecha desde (fromDate)');
    } else {
      $purchase = Purchase::all();
      $messages = array('status' => 'ok', 'data' => $purchase);
    }
  }

  return $response->withJson($messages);
});


// Login to the API
$app->post('/login', function(Request $request, Response $response, array $args) {
  $headerAuthentication = $request->getHeader('authentication');
  $headerTimestamp = $request->getHeader('timestamp');

  //Check timestamp validation

  if(empty($headerAuthentication) || empty($headerTimestamp)) {
    $messages = array('error' => 'Cabeceras no encontradas');
  } else {
    if(strpos(strtoupper($headerAuthentication[0]), 'HMAC ') === false) {
      $messages = array('error' => 'Cabecera de autenticación no hallada');
    } else {
      $authKeySig = substr($headerAuthentication, 5);
      if(count(explode(':', $authKeySig)) !== 2) {
        $messages = array('error' => 'La cabecera de autenticacion no es válida');
      } else {
        list($publicKey, $hmacSignature) = explode(':', $authKeySig);

        $secretKey = ApiKey::where('public_id', $publicKey)->value('secret_key')->first();
        if($secretKey === null) {
          $messages = array('error' => 'Clave pública no válida');
        } else {
          $payload = $request->getMethod() . '\n';
          $payload .= $request->getUri() . '\n';
          $payload .= $headerTimestamp . '\n';

          $hash = hash_hmac('sha256', $payload, $secretKey, false);

          if($hmacSignature === $hash) {
            $messages = array('status' => 'TOKEN');
          } else {
            $messages = array('status' => 'Signature no válido');
          }
        }
      }
    }
  }

  return $response->withJson($messages);
});
