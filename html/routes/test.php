<?php
  require __DIR__ . '/../controllers/autoload.php';

  $t = new connect();
  var_dump($t -> query('SELECT UNIX_TIMESTAMP(`timestamp`) as `ts` FROM `refresh_tokens` LIMIT 1;') -> fetch_assoc());
  $t -> close();