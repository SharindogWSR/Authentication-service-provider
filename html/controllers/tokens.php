<?php
  /*
    Контроллер Tokens.php.
    Предназначен генерации, проверки и прочих вещей с JWT-токенами.
    Спецефикация передаваемых значений в токене:
      - iss (Issuer)          Передается значение URL-адреса сервиса авторизации.
      - iat (Issued At)       Время в UNIX-формате, которое показывает время выдачи токена.
      - exp (Expiration Time) Время в UNIX-формате, которое показывает время окончания работы токена.
      - aud (Audience)        Имя сервиса, который запросил токен. Если запрос токена произошел для работы с сервисом авторизации, то передается имя "authorization".
      - sub (Subject)         UUID пользователя, для которого был сгенерирован токен.

    В независимости от действий, время жизни JWT-токена составляет 30 минут. Время жизни Refresh Token - 30 дней.
  */

class tokens {
    private $keys = [
      'public' => '',
      'private' => '',
    ];
    private $firebase = [
      'pub_key' => '',
      'class' => []
    ];
    private $config = [];

    public function __construct() {
      require __DIR__ . '/../vendor/autoload.php';
      require __DIR__ . '/../assets/php/keys.php';
      require __DIR__ . '/../assets/php/configuration.php';
      $this -> keys['public'] = $keys['public'];
      $this -> keys['private'] = $keys['private'];
      $this -> config = $CNF['jwt'];
      unset($CNF);
      unset($keys);
      $this -> firebase['pub_key'] = new Firebase\JWT\Key($this -> keys['public'], 'RS512');
      $this -> firebase['class'] = new \Firebase\JWT\JWT;
    }

    public function create_jwt_token(array $user_data = [], string $uuid = '', string $service_name = 'authorization') {
      return $this -> firebase['class'] -> encode(
        [
          'iss' => $this -> config['iss'],
          'iat' => time(),
          'exp' => time() + $this -> config['lifetime'],
          'aud' => $service_name,
          'sub' => $uuid,
          'user_data' => $user_data
        ],
        $this -> keys['private'],
        'RS512'
      );
    }

    public function create_refresh_token(string $jwt = '') {
      return hash(
        'SHA512',
        $jwt . time() . bin2hex(random_bytes(1024))
      );
    }

    public function decode_jwt_token(string $jwt = '') {
      if (!empty($jwt)) {
        $ret = null;
        try {
          $ret = [true, $this -> firebase['class'] -> decode(
            $jwt, 
            $this -> firebase['pub_key']
          )];
        } catch (Exception $e) {
          $ret = [false, $e -> getMessage()];
        }
        return $ret;
      }
      else return [false, 'Не предоставлен JWT-токен.'];
    }

    public function check_data_of_jwt(string $jwt = '', string $service = '', string $group = '')  {

    }
  }