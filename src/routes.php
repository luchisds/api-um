<?php

use Slim\Http\Request;
use Slim\Http\Response;

// Routes

// Update price and stock by product color
$app->post('/v1/productos/{id}', function(Request $request, Response $response, array $args) {
  $id = $args['id'];
  $data = array('message' => 'Actualización correcta', 'product' => $id);

  return $response->withJson($data);
});

// Update purchases by product
$app->post('/v1/compras/{id}', function(Request $request, Response $response, array $args) {
  $id = $args['id'];
  $data = array('message' => 'Compras actualizadas', 'product' => $id);

  return $response->withJson($data);
});

// Check purchases made between two dates
$app->get('/v1/compras', function(Request $request, Response $response, array $args) {
  $data = array('message' => 'Listado de compras');

  return $response->withJson($data);
});

// Login to the API
$app->post('/login', function(Request $request, Response $response, array $args) {
  $data = array('message' => 'Login');

  return $response->withJson($data);
});
