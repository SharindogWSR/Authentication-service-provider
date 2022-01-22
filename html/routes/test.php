<?php
  require __DIR__ . '/../controllers/autoload.php';

  $t = new tokens();

  print_r($t -> create_jwt_token(['foo' => 'bar'], '79bc76ef-94d1-4cbd-e2e8-9d842d6b85a6', 'test-service'));