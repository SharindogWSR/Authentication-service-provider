<?php
  /* 
    Контроллер Database.php.
    Данный контроллер предназачен для работы с базой данных, подключением и т.п.
  */

  class connect extends mysqli {
    public function __construct() {
      require __DIR__ . '/../assets/php/dbase_connect.php';
      if (!empty($dbase_connect)) {
        parent::__construct(
          $dbase_connect['hostname'],
          $dbase_connect['username'],
          $dbase_connect['password'],
          $dbase_connect['database']
        );
        unset($dbase_connect);
      } else throw new ErrorException('Подключение к базе данных не настроено!');
    }

    static function check_connect(string $hostname = '', string $username = '', string $password = '', string $database = '') {
      $sql= (object) [];
      try {
        $sql = new mysqli(
          $hostname,
          $username,
          $password,
          $database
        );
        $sql -> close();
        return $sql;
      } catch (ErrorException $e) {
        return 'Ошибка при подключении к базе данных. Ошибка в предоставленных данных.';
      }
    }

    // НАЧАЛО БЛОКА ФУНКЦИЙ ПОЛЬЗОВАТЕЛЕЙ

    public function check_uuid(string $uuid = '') {
      if (!empty($uuid)) {
        $statement = $this -> prepare("SELECT `id` FROM `authorization` WHERE `uuid` = ?;");
        $statement -> bind_param('s', $this -> real_escape_string($uuid));
        $statement -> execute();
        return $statement -> get_result() -> num_rows == 1 ? true : false;
      } else return false;
    }

    public function get_user(string $uuid = '', string $service_token = '') {
      if ($this -> check_uuid($uuid)) {
        if (!empty($service_token)) {
          $statement = $this -> prepare("
            SELECT
              users_data.lastname, users_data.firstname, users_data.patronymic,
              users_data.group AS system_group, users_data.payload, authorization.email,
              authorization.google_ldap_email, services_authorization.group AS service_group,
              services.production, services.payload AS isPayload, services.name AS services_name
            FROM 
              (
                (
                  users_data INNER JOIN authorization ON users_data.id = authorization.id_data
                ) INNER JOIN services_authorization ON authorization.id = services_authorization.id_user
			          INNER JOIN services ON services_authorization.id_service = services.id
              ) WHERE services.token = ? AND authorization.uuid = ?;
          ");
          $statement -> bind_param(
            'ss',
            $this -> real_escape_string($service_token),
            $this -> real_escape_string($uuid)
          );
          $statement -> execute();
          if ($statement -> get_result() -> num_rows == 1) {
            $statement = $statement -> get_result() -> fetch_assoc();
            if (boolval($statement['production']) || $statement['system_role'] == 'system') {
              $user = [
                'lastname' => $statement['lastname'],
                'firstname' => $statement['firstname'],
                'patronymic' => $statement['patronymic'],
                'system_group' => $statement['system_group'],
                'email' => $statement['email'],
                'google_ldap_email' => $statement['google_ldap_email'],
                'service' => [
                  'group' => $statement['service_group']
                ]
              ];
              if (boolval($statement['isPayload'])) {
                $user['payload'] = json_decode($statement['payload']) -> {$statement['services_name']};
              }
              return $user;
            } else return false;
          } else return false;
        } else {
          $statement = $this -> prepare("
            SELECT `lastname`, `firstname`, `patronymic`,
            `group`, `email`, `google_ldap_email`
            FROM `authorization` INNER JOIN `users_data`
            ON `authorization`.`id_data` = `users_data`.`id`
            WHERE `authorization`.`uuid` = ?;"
          );
          $statement -> bind_param('s', $this -> real_escape_string($uuid));
          $statement -> execute();
          return $statement -> get_result() -> num_rows == 1 ? $statement -> get_result() -> fetch_assoc() : false;
        }
      } else return false;
    }

    public function user_login(string $email = '', string $password = '', string $user_agent = '', string $ip = '', int $id_service = 0) {
      if (!empty($email) && !empty($password)) {
        $email = $this -> real_escape_string($email);
        $password = $this -> real_escape_string($password);
        $statement = $this -> prepare("SELECT `id`, `uuid`, `password_hash` FROM `authorization` WHERE `email` = ?;");
        $statement -> bind_param('s', $email);
        $statement -> execute();
        if ($statement -> get_result() -> num_rows == 1) {
          $statement = $statement -> get_result() -> fetch_assoc();
          if (password_verify($password, $statement['password_hash'])) {
            $id_user = intval($statement['id']);
            $uuid = $statement['uuid'];
            $user_agent = $this -> real_escape_string($user_agent);
            $ip = $this -> real_escape_string($ip);
            $statement = $this -> prepare("INSERT INTO `log_of_authorization` (`id_user`, `user_agent`, `ip_address`, `timestamp`, `id_service`) VALUES (?, ?, ?, ?, ?);");
            $statement -> bind_param('issii', $id_user, $user_agent, $ip, time(), $id_service);
            $statement -> execute();
            return $uuid;
          }
        } else return false;
      } else return false;
    }

    public function save_refresh_token(string $uuid = '', string $refresh = '', string $user_agent = '') {
      if (!empty($uuid) && !empty($refresh) && !empty($user_agent)) {
        if ($this -> get_user($uuid)) {
          $refresh = hash('SHA512', $refresh);
          $user_agent = $this -> real_escape_string($user_agent);
          $check_exsist = $this -> prepare("SELECT `timestamp` FROM `refresh_tokens` WHERE `user_agent` = ?;");
          $check_exsist -> bind_param('s', $refresh);
          $check_exsist -> execute();
          $statement = null;
          if ($check_exsist -> get_result() -> num_rows == 0) {
            $statement = $this -> prepare("
              INSERT INTO `refresh_tokens`
              (
                `id_user`, `tokens_hash`, `timestamp`,
                `user_agent`
              ) VALUES (
                (
                  SELECT `id`
                  FROM `authorization`
                  WHERE `uuid` = ?
                ),
                ?,
                ?,
                ?
              );
            ");
            $statement -> bind_param('ssis', $uuid, $refresh, time(), $user_agent);
          } else {
            $statement = $this -> prepare("UPDATE `refresh_tokens` SET `tokens_hash` = ?, `timestamp` = ? WHERE `user_agent` = ?;");
            $statement -> bind_param('sis', $refresh, time(), $user_agent);
          }
          $statement -> execute();
          return true;
        } else return false;
      } else return false;
    }

    // НАЧАЛО БЛОКА ФУНКЦИЙ СЕРВИСОВ

    public function list_of_services(string $token = '') {
      $returned = [];
      $s = null;
      if (!empty($token)) {
        $s = $this -> prepare("SELECT `name`, `production`, `payload`, `groups`, `can_edit_user` FROM `services` WHERE `token_hash` = ?;");
        $s -> bind_param('s', hash('SHA512', $token));
      } else $s = $this -> prepare("SELECT `name`, `production`, `payload`, `groups`, `can_edit_user` FROM `services`");
      $s -> execute();
      while ($row = $s -> get_result() -> fetch_assoc())
        $returned[] = [
          'name' => $row['name'],
          'production' => boolval($row['production']),
          'payload' => boolval($row['payload']),
          'groups' => boolval($row['groups']),
          'can_edit_user' => boolval($row['can_edit_user'])
        ];
      return $returned;
    }

    public function create_service(
      string $name = '',
      string $token = '',
      bool $b_production = false,
      bool $b_payload = false,
      bool $b_can_edit_user = false,
      array $b_groups = []
    ) {
      if (!empty($name) && !empty($token) && !empty($b_groups)) {
        $token = hash('SHA512', $token);
        $check_exsist = $this -> prepare("SELECT `id` FROM `services` WHERE `token_hash` = ?;");
        $check_exsist -> bind_param('s', hash('SHA512', $token));
        if ($check_exsist -> get_result() -> num_rows == 0) {
          $insert = $this -> prepare("INSERT INTO `services` (`token_hash`, `name`, `production`, `payload`, `groups`, `can_edit_user`) VALUES (?, ?, ?, ?, ?, ?);");
          $insert -> bind_param(
            'ssiiii',
            $token,
            $this -> real_escape_string($name),
            intval($b_production),
            intval($b_payload),
            intval($b_groups),
            intval($b_can_edit_user)
          );
          $insert -> execute();
          return true;
        } else return false;
      } else return false;
    }
  }