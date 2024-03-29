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

    public function check_uuid_exsist(string $uuid = '') {
      if (!empty($uuid)) {
        $statement = $this -> prepare("SELECT `id` FROM `authorization` WHERE `uuid` = ?;");
        $uuid = $this -> real_escape_string($uuid);
        $statement -> bind_param('s', $uuid);
        $statement -> execute();
        return $statement -> get_result() -> num_rows == 1;
      } else return false;
    }

    public function check_email_exsist(string $email = '') {
      if (!empty($email)) {
        $statement = $this -> prepare("SELECT `id` FROM `authorization` WHERE `email` = ?;");
        $email = $this -> real_escape_string($email);
        $statement -> bind_param('s', $email);
        $statement -> execute();
        return $statement -> get_result() -> num_rows == 1;
      } else return false;
    }

    public function get_user(string $uuid = '', string $service_token = '') {
      if ($this -> check_uuid_exsist($uuid)) {
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
          $statement = $statement -> get_result();
          if ($statement -> num_rows == 1) {
            $statement = $statement -> fetch_assoc();
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
          $uuid = $this -> real_escape_string($uuid);
          $statement -> bind_param('s', $uuid);
          $statement -> execute();
          $statement = $statement -> get_result();
          return $statement -> num_rows == 1 ? $statement -> fetch_assoc() : false;
        }
      } else return false;
    }

    public function user_login(string $email = '', string $password = '', string $user_agent = '', string $ip = '', int $id_service = 0) {
      if (!empty($email) && !empty($password)) {
        $email = $this -> real_escape_string($email);
        $password = $this -> real_escape_string($password);
        $statement = $this -> prepare("
          SELECT
            `authorization`.`id`, `uuid`, `password_hash`,
            `users_data`.`group` 
          FROM `authorization` 
          INNER JOIN `users_data`
          ON `authorization`.`id_data` = `users_data`.`id`
          WHERE `email` = ?;
        ");
        $statement -> bind_param('s', $email);
        $statement -> execute();
        $statement = $statement -> get_result();
        if ($statement -> num_rows == 1) {
          $statement = $statement -> fetch_assoc();
          if (password_verify($password, $statement['password_hash'])) {
            $id_user = intval($statement['id']);
            $uuid = $statement['uuid'];
            $user_agent = $this -> real_escape_string($user_agent);
            $ip = $this -> real_escape_string($ip);
            if ($statement['group'] == 'system' && $id_service == 0) {
              $statement = $this -> prepare("INSERT INTO `log_of_authorization` (`id_user`, `user_agent`, `ip_address`, `timestamp`, `id_service`) VALUES (?, ?, ?, NOW(), NULL);");
              $statement -> bind_param('iss', $id_user, $user_agent, $ip);
              $statement -> execute();
              return $uuid;
            } elseif ($id_service != 0) {
              $statement = $this -> prepare("INSERT INTO `log_of_authorization` (`id_user`, `user_agent`, `ip_address`, `timestamp`, `id_service`) VALUES (?, ?, ?, NOW(), ?);");
              $statement -> bind_param('issi', $id_user, $user_agent, $ip, $id_service);
              $statement -> execute();
              return $uuid;
            } else return false;
          }
        } else return false;
      } else return false;
    }

    public function save_refresh_token(string $uuid = '', string $refresh = '', string $user_agent = '', int $id_service = 0) {
      if (!empty($uuid) && !empty($refresh) && !empty($user_agent)) {
        if ($this -> get_user($uuid)) {
          $refresh = hash('SHA512', $refresh);
          $user_agent = $this -> real_escape_string($user_agent);
          $check_exsist = null;
          if ($id_service == 0) {
            $check_exsist = $this -> prepare("
              SELECT
                `timestamp`
              FROM
                `refresh_tokens`
              WHERE
                `user_agent` = ? AND
                `id_user` = (
                  SELECT `id`
                    FROM `authorization`
                    WHERE `uuid` = ?
                ) AND
                `id_service` IS NULL;
            ");
            $check_exsist -> bind_param('ss', $user_agent, $uuid);
          } else {
            $check_exsist = $this -> prepare("
              SELECT
                `timestamp`
              FROM
                `refresh_tokens`
              WHERE
                `user_agent` = ? AND
                `id_user` = (
                  SELECT `id`
                    FROM `authorization`
                    WHERE `uuid` = ?
                ) AND
                `id_service` = ?;
            ");
            $check_exsist -> bind_param('ssi', $user_agent, $uuid, $id_service);
          }
          $check_exsist -> execute();
          $statement = null;
          if ($check_exsist -> get_result() -> num_rows == 0) {
            if ($id_service != 0) {
              $statement = $this -> prepare("
                INSERT INTO `refresh_tokens`
                (
                  `id_user`, `tokens_hash`, `timestamp`,
                  `user_agent`, `id_service`
                ) VALUES (
                  (
                    SELECT `id`
                    FROM `authorization`
                    WHERE `uuid` = ?
                  ),
                  ?,
                  NOW(),
                  ?,
                  ?
                );
              ");
              $statement -> bind_param('sssi', $uuid, $refresh, $user_agent, $id_service);
            } else {
              $statement = $this -> prepare("
                INSERT INTO `refresh_tokens`
                (
                  `id_user`, `tokens_hash`, `timestamp`,
                  `user_agent`, `id_service`
                ) VALUES (
                  (
                    SELECT `id`
                    FROM `authorization`
                    WHERE `uuid` = ?
                  ),
                  ?,
                  NOW(),
                  ?,
                  NULL
                );
              ");
              $statement -> bind_param('sss', $uuid, $refresh, $user_agent);
            }
          } else {
            if ($id_service != 0) {
              $statement = $this -> prepare("
                UPDATE `refresh_tokens`
                SET `tokens_hash` = ?, `timestamp` = NOW()
                WHERE
                  `user_agent` = ? AND
                  `id_user` = (
                      SELECT `id`
                      FROM `authorization`
                      WHERE `uuid` = ?
                  ),
                  `id_service` = ?
                ;
              ");
              $statement -> bind_param('sssi', $refresh, $user_agent, $uuid, $id_service);
            } else {
              $statement = $this -> prepare("
                UPDATE `refresh_tokens`
                SET `tokens_hash` = ?, `timestamp` = NOW()
                WHERE
                  `user_agent` = ? AND
                  `id_user` = (
                      SELECT `id`
                      FROM `authorization`
                      WHERE `uuid` = ?
                  ) AND
                  `id_service` IS NULL;
              ");
              $statement -> bind_param('sss', $refresh, $user_agent, $uuid);
            }
          }
          $statement -> execute();
          return true;
        } else return false;
      } else return false;
    }

    public function check_refresh_token(string $refresh = '', string $user_agent = '') {
      if (!empty($refresh)) {
        $refresh = hash('SHA512', $refresh);
        $user_agent = $this -> real_escape_string($user_agent);
        $get_refresh = $this -> prepare('
          SELECT
            UNIX_TIMESTAMP(`timestamp`) AS `ts`,
            `authorization`.`uuid`,
            IF (
              id_service IS NULL,
              \'authorization\',
              (
                SELECT `name`
                FROM `services`
                WHERE `id` = `id_service`
              )
            ) AS `service_name`
          FROM `refresh_tokens`
          INNER JOIN `authorization`
          ON `refresh_tokens`.`id_user` = `authorization`.`id`
          WHERE `refresh_tokens`.`tokens_hash` = ? AND `refresh_tokens`.`user_agent` = ?;
        ');
        $get_refresh -> bind_param('ss', $refresh, $user_agent);
        $get_refresh -> execute();
        $get_refresh = $get_refresh -> get_result();
        if ($get_refresh -> num_rows == 1) {
          $get_refresh = $get_refresh -> fetch_assoc();
          if ((intval($get_refresh['ts']) + 60 * 60 * 24 * 30) > time())
            return [
              'uuid' => $get_refresh['uuid'],
              'service' => $get_refresh['service_name'],
            ];
          else {
            $get_refresh = $this -> prepare('DELETE FROM `refresh_tokens` WHERE `tokens_hash` = ?;');
            $get_refresh -> bind_param('s', $refresh);
            $get_refresh -> execute();
            return 2;
          }
        } else {
          $get_refresh = $this -> prepare('DELETE FROM `refresh_tokens` WHERE `tokens_hash` = ?;');
          $get_refresh -> bind_param('s', $refresh);
          $get_refresh -> execute();
          return 1;
        }
      } else return 0;
    }

    public function purge_refresh_token(string $refresh = '', string $user_agent = '') {
      if (!empty($refresh) && !empty($user_agent)) {
        if (is_array($this -> check_refresh_token($refresh, $user_agent))) {
          $refresh = hash('SHA512', $refresh);
          $get_refresh = $this -> prepare('DELETE FROM `refresh_tokens` WHERE `tokens_hash` = ?;');
          $get_refresh -> bind_param('s', $refresh);
          $get_refresh -> execute();
          return true;
        } else return false;
      } else return false;
    }

    public function register_user(
      string $email = '',
      string $lastname = '',
      string $firstname = '',
      string $patronymic = '',
      string $group = ''
    ) {
      if (
        !empty($email) &&
        !empty($lastname) &&
        !empty($firstname) &&
        !empty($group)
      ) {
        if (!$this -> check_email_exsist($email)) {
          $uuid = system::create_UUID();
          while ($this -> check_uuid_exsist($uuid)) {
            $uuid = system::create_UUID();
          }
          $password[] = system::create_password();
          $password[] = password_hash($password[0], PASSWORD_DEFAULT);
          $insert_user_data = $this -> prepare("INSERT INTO `users_data` (`lastname`, `firstname`, `patronymic`, `group`, `payload`) VALUES (?, ?, ?, ?, NULL);");
          $email = $this -> real_escape_string($email);
          $firstname = $this -> real_escape_string($firstname);
          $lastname = $this -> real_escape_string($lastname);
          $patronymic = $this -> real_escape_string($patronymic);
          $group = $this -> real_escape_string($group);
          $insert_user_data -> bind_param('ssss', $lastname, $firstname, $patronymic, $group);
          $insert_user_data -> execute();
          $insert_user_data = $insert_user_data -> insert_id;
          $insert_auth = $this -> prepare("INSERT INTO `authorization` (`uuid`, `email`, `google_ldap_email`, `password_hash`, `id_data`) VALUES (?, ?, NULL, ?, ?);");
          $insert_auth -> bind_param('sssi', $uuid, $email, $password[1], $insert_user_data);
          $insert_auth -> execute();
          return [
            'uuid' => $uuid,
            'password' => $password[0]
          ];
        } else return false;
      } else return false;
    }

    public function purge_user(string $uuid = '') {
      if (!empty($uuid)) {
        if ($this -> check_uuid_exsist($uuid)) {
          $auth_id = $this -> prepare("SELECT `id`, `id_data` FROM `authorization` WHERE `uuid` = ?;");
          $uuid = $this -> real_escape_string($uuid);
          $auth_id -> bind_param('s', $uuid);
          $auth_id -> execute();
          $auth_id = $auth_id -> get_result() -> fetch_assoc();
          $this -> multi_query("
            DELETE FROM `refresh_tokens` WHERE `id_user` = {$auth_id['id']};
            DELETE FROM `services_authorization` WHERE `id_user` = {$auth_id['id']};
            DELETE FROM `log_of_authorization` WHERE `id_user` = {$auth_id['id']};
            DELETE FROM `authorization` WHERE `id` = {$auth_id['id']};
            DELETE FROM `users_data` WHERE `id` = {$auth_id['id_data']};
          ");
          return true;
        } else return false;
      } else return false;
    }

    // НАЧАЛО БЛОКА ФУНКЦИЙ СЕРВИСОВ

    public function list_of_services($token = '') {
      $returned = [];
      $s = null;
      if (!empty($token)) {
        if (is_numeric($token)) {
          $s = $this -> prepare("SELECT `id`, `name`, `production`, `payload`, `groups`, `can_edit_user`, `can_get_list_of_services` FROM `services` WHERE `id` = ?;");
          $token = intval($token);
          $s -> bind_param('i', $token);
        } else {
          $s = $this -> prepare("SELECT `id`, `name`, `production`, `payload`, `groups`, `can_edit_user`, `can_get_list_of_services` FROM `services` WHERE `token_hash` = ?;");
          $token = hash('SHA512', $token);
          $s -> bind_param('s', $token);
        }
      } else $s = $this -> prepare("SELECT `id`, `name`, `production`, `payload`, `groups`, `can_edit_user`, `can_get_list_of_services` FROM `services`");
      $s -> execute();
      $s = $s -> get_result();
      while ($row = $s -> fetch_assoc())
        $returned[] = [
          'id' => intval($row['id']),
          'name' => $row['name'],
          'production' => boolval($row['production']),
          'payload' => boolval($row['payload']),
          'groups' => json_decode($row['groups']),
          'can_edit_user' => boolval($row['can_edit_user']),
          'can_get_list_of_services' => boolval($row['can_get_list_of_services'])
        ];
      return $returned;
    }

    public function create_service(
      string $name = '',
      string $token = '',
      bool $b_production = false,
      bool $b_payload = false,
      bool $b_can_edit_user = false,
      bool $b_can_get_list_of_services = false,
      object $b_groups
    ) {
      if (!empty($name) && !empty($token) && !empty($b_groups)) {
        $token = hash('SHA512', $token);
        $check_exsist = $this -> prepare("SELECT `id` FROM `services` WHERE `token_hash` = ? OR `name` = ?;");
        $check_exsist -> bind_param('ss', $token, $name);
        $check_exsist -> execute();
        if ($check_exsist -> get_result() -> num_rows == 0) {
          $insert = $this -> prepare("INSERT INTO `services` (`token_hash`, `name`, `production`, `payload`, `groups`, `can_edit_user`, `can_get_list_of_services`) VALUES (?, ?, ?, ?, ?, ?, ?);");
          $name = $this -> real_escape_string($name);
          $b_production = intval($b_production);
          $b_can_edit_user = intval($b_can_edit_user);
          $b_can_get_list_of_services = intval($b_can_get_list_of_services);
          $b_groups = json_encode($b_groups);
          $insert -> bind_param(
            'ssiisii',
            $token,
            $name,
            $b_production,
            $b_payload,
            $b_groups,
            $b_can_edit_user,
            $b_can_get_list_of_services
          );
          $insert -> execute();
          return true;
        } else return false;
      } else return false;
    }

    public function edit_service(array $payload = []) {
      $token_hash = !empty($payload['token']) ? hash('SHA512', $payload['token']) : null;
      $name = !empty($payload['name']) ? $payload['name'] : null;
      $groups = !empty($payload['groups']) ? $payload['groups'] : null;
      $triggers = null;
      $pre = [
        'types' => '',
        'sql' => '',
        'payload' => []
      ];

      foreach ($payload as $key => $value)
        if (in_array($key, ['production', 'payload', 'can_edit_user', 'can_get_list_of_services']))
          $triggers[$key] = intval($value);
      if (!empty($this -> list_of_services($payload['token']))) {
        if (!is_null($name)) {
          $pre['types'] .= 's';
          $pre['sql'] .= '`name` = ?, ';
          $pre['payload'][] = $name;
        }
        if (!is_null($groups)) {
          $pre['types'] .= 's';
          $pre['sql'] .= '`groups` = ?, ';
          $pre['payload'][] = $groups;
        }
        if (!is_null($triggers)) {
          foreach ($triggers as $key => $value) {
            $pre['types'] .= 'i';
            $pre['sql'] .= "`{$key}` = ?, ";
            $pre['payload'][] = $value;
          }
        }
        if (!empty($pre['types'])) {
          $pre['sql'] = substr($pre['sql'], 0, -2);
          $stmt = $this -> prepare("UPDATE `services` SET {$pre['sql']} WHERE `token_hash` = ?;");
          $pre['types'] .= 's';
          $pre['payload'][] = $token_hash;
          $stmt -> bind_param($pre['types'], ...$pre['payload']);
          $stmt -> execute();
          return true;
        } else return false;
      } else return false;
    }

    public function purge_service(string $token = '') {
      if (!empty($token)) {
        if (!empty($this -> list_of_services($token))) {
          $token = hash('SHA512', $token);
          $stmt = $this -> prepare("DELETE FROM `services` WHERE `token_hash` = ?;");
          $stmt -> bind_param('s', $token);
          $stmt -> execute();
          return true;
        } else return false;
      } else return false;
    }

    public function get_new_refresh_token_for_service($identity = '') {
      if (!empty($identity)) {
        $service = $this -> list_of_services(is_numeric($identity) ? intval($identity) : $identity);
        if (!empty($service)) { 
          $token = hash('SHA512', $service[0]['name'] . time());
          $thash = hash('SHA512', $token);
          $stmt = null;
          if (is_numeric($identity)) {
            $stmt = $this -> prepare("UPDATE `services` SET `token_hash` = ? WHERE `id` = ?;");
            $stmt -> bind_param('si', $thash, $identity);
          } else {
            $stmt = $this -> prepare("UPDATE `services` SET `token_hash` = ? WHERE `token_hash` = ?;");
            $stmt -> bind_param('ss', $thash, $identity);
          }
          $stmt -> execute();
          return $token;
        } else return false;
      } else return false;
    }
  }