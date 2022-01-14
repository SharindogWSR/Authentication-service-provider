<?php
  /*
    Контроллер System.php.
    Используется для вызова функций системы.
  */

  class system {
    static function create_UUID() {
      return sprintf(
        "%s-%s-4%s-e%s-%s",
        bin2hex(openssl_random_pseudo_bytes(4)),
        bin2hex(openssl_random_pseudo_bytes(2)),
        substr(bin2hex(openssl_random_pseudo_bytes(2)), 0, 3),
        substr(bin2hex(openssl_random_pseudo_bytes(2)), 0, 3),
        bin2hex(openssl_random_pseudo_bytes(6))
      );
    }
  
    static function create_message(string $message = '', array $payload = [], int $status = 200, bool $json = true) {
      http_response_code($status);
      $answer['message'] = $message;
      if (!empty($payload))
        $answer['payload'] = $payload;
      $answer['timestamp'] = time();
      echo $json ? json_encode($answer) : $answer;
    }
  
    static function check_required_payload(array $payload = [], string $type = 'POST') {
      if (!empty($payload)) {
        $check = $type == 'POST' ? $_POST : $_GET;
        $not_found = [];
        foreach ($payload as $value)
          if (!array_key_exists($value, $check))
            $not_found[] = $value;
        return $not_found;
      } else return [];
    }
  
    static function check_method(array $allowed = ['POST']) {
      return in_array($_SERVER['REQUEST_METHOD'], $allowed);
    }
  }