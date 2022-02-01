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
        $uuid = $this -> real_escape_string($uuid);
        $statement -> bind_param('s', $uuid);
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

    // НАЧАЛО БЛОКА ФУНКЦИЙ СЕРВИСОВ

    public function list_of_services(string $token = '') {
      $returned = [];
      $s = null;
      if (!empty($token)) {
        $s = $this -> prepare("SELECT `id`, `name`, `production`, `payload`, `groups`, `can_edit_user`, `can_get_list_of_services` FROM `services` WHERE `token_hash` = ?;");
        $token = hash('SHA512', $token);
        $s -> bind_param('s', $token);
      } else $s = $this -> prepare("SELECT `id`, `name`, `production`, `payload`, `groups`, `can_edit_user`, `can_get_list_of_services` FROM `services`");
      $s -> execute();
      $s = $s -> get_result();
      while ($row = $s -> fetch_assoc())
        $returned[] = [
          'id' => intval($row['id']),
          'name' => $row['name'],
          'production' => boolval($row['production']),
          'payload' => boolval($row['payload']),
          'groups' => boolval($row['groups']),
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
      array $b_groups = []
    ) {
      if (!empty($name) && !empty($token) && !empty($b_groups)) {
        $token = hash('SHA512', $token);
        $check_exsist = $this -> prepare("SELECT `id` FROM `services` WHERE `token_hash` = ? OR `name` = ?;");
        $check_exsist -> bind_param('ss', $token, $name);
        if ($check_exsist -> get_result() -> num_rows == 0) {
          $insert = $this -> prepare("INSERT INTO `services` (`token_hash`, `name`, `production`, `payload`, `groups`, `can_edit_user`, `can_get_list_of_services`) VALUES (?, ?, ?, ?, ?, ?, ?);");
          $insert -> bind_param(
            'ssiiiii',
            $token,
            $this -> real_escape_string($name),
            intval($b_production),
            intval($b_payload),
            intval($b_groups),
            intval($b_can_edit_user),
            intval($b_can_get_list_of_services)
          );
          $insert -> execute();
          return true;
        } else return false;
      } else return false;
    }
  }