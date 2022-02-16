<?php
  require __DIR__ . '/../../assets/php/configuration.php';
  require __DIR__ . '/../../assets/php/keys.php';

  print(json_encode([
    'jwt' => [
      'public_key' => base64_encode($keys['public']),
      'lifetime' => $CNF['jwt']['lifetime'],
    ],
    'url' => $CNF['jwt']['iss'],
  ]));