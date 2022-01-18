<?php
  require __DIR__ . '/../controllers/autoload.php';

  $database = new connect();

  print_r($database -> get_user('79bc76ef-94d1-4cbd-e2e8-9d842d6b85a6'));

  $database -> close();