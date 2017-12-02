<?php

use Respect\Validation\Validator as v;
use Respect\Validation\Exceptions\NestedValidationException as Exception;


// Validation Rules

$productIdRule = v::intVal()->positive();
$priceRule = v::floatType()->positive();
$stockRule = v::intType()->positive();
$quantityRule = v::intType()->positive();
$dateRule = v::notEmpty()->date('Y-m-d');
$invoiceRule = v::stringType()->alnum('-')->noWhitespace()->length(13, 13);


// Validators

$updateProductValidator = v::attribute('product_id', $productIdRule)
                          ->attribute('price', $priceRule)
                          ->attribute('stock', $stockRule);

$updatePurchaseValidator = v::attribute('product_id', $productIdRule)
                            ->attribute('quantity', $quantityRule)
                            ->attribute('date', $dateRule)
                            ->attribute('invoice', $invoiceRule);

$getPurchaseValidator = v::attribute('fromDate', $dateRule)
                         ->attribute('toDate', $dateRule);


// Validation Functions

function validateProductUpdate($data) {
  global $updateProductValidator;
  $result = false;
  $errors = null;

  try {
    $result = $updateProductValidator->assert($data);
  } catch(Exception $exception) {
    $errors = $exception->getMessages();
  }

  return array(
    'isValid' => $result,
    'errors' => $errors
  );
}

function validatePurchaseUpdate($data) {
  global $updatePurchaseValidator;
  $result = false;
  $errors = null;

  try {
    $result = $updatePurchaseValidator->assert($data);
  } catch(Exception $exception) {
    $errors = $exception->getMessages();
  }

  return array(
    'isValid' => $result,
    'errors' => $errors
  );
}

function validatePurchaseCheck($data) {
  global $getPurchaseValidator;
  $result = false;
  $errors = null;

  try {
    $result = $getPurchaseValidator->assert($data);
  } catch(Exception $exception) {
    $errors = $exception->getMessages();
  }

  return array(
    'isValid' => $result,
    'errors' => $errors
  );
}
