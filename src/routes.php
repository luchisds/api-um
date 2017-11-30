<?php

use Slim\Http\Request;
use Slim\Http\Response;

// Import db models
require __DIR__ . '/../src/models/product.php';
require __DIR__ . '/../src/models/purchase.php';

// Routes

// Update price and stock by product color
$app->post('/v1/productos/{id}', function(Request $request, Response $response, array $args) {
  $data = $request->getParsedBody();
  $productId = $args['id'];

  $product = Product::where('product_id', $productId)->first();
  $product->price = $data['price'];
  $product->stock = $data['stock'];
  $product->save();

  $messages = array('status' => 'Actualización correcta');
  return $response->withJson($messages);
});


// Update purchases by product
$app->post('/v1/compras/{id}', function(Request $request, Response $response, array $args) {
  $data = $request->getParsedBody();
  $productId = $args['id'];

  $purchase = new Purchase;
  $purchase->product_id = $productId;
  $purchase->quantity = $data['quantity'];
  $purchase->date = $data['date'];
  $purchase->invoice = $data['invoice'];
  $purchase->save();

  $messages = array('status' => 'Actualización correcta');
  return $response->withJson($messages);
});


// Check purchases made between two dates
$app->get('/v1/compras', function(Request $request, Response $response, array $args) {
  $data = $request->getQueryParams();

  if(isset($data['fromDate']) && isset($data['toDate'])) {
    $purchase = Purchase::whereBetween('date', [$data['fromDate'], $data['toDate']])->get();
  } else {
    $purchase = Purchase::all();
  }

  $messages = array('purchases' => $purchase);
  return $response->withJson($messages);
});


// Login to the API
$app->post('/login', function(Request $request, Response $response, array $args) {
  $data = array('message' => 'Login');

  return $response->withJson($data);
});
