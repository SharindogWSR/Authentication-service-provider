<?php
  /*
    Контроллер Tokens.php.
    Предназначен генерации, проверки и прочих вещей с JWT-токенами.
  */

  class tokens {
    private $keys = [
      'public' => '',
      'private' => '',
    ];
    private $firebase = [
      'pub_key' => '',
    ];

    public function __construct() {
      require __DIR__ . '/../vendor/autoload.php';
      require __DIR__ . '/../assets/php/keys.php';
      $this -> keys['public'] = $keys['public'];
      $this -> keys['private'] = $keys['private'];
      unset($keys);
      $this -> firebase['pub_key'] = new Firebase\JWT\Key($this -> keys['public'], 'RS512');
    }
  }