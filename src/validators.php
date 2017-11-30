<?php

use Respect\Validation\Validator as v;

// Validators
$productIdValidator = v::intType()->positive();
$priceValidator = v::floatType()->positive();
$stockValidator = v::intType()->positive();
$quantityValidator = v::intType()->positive();
$dateValidator = v::date('Y-m-d');
$invoiceValidator = v::stringType()->alnum('-')->noWhitespace()->length(13, 13);
