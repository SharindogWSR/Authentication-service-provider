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

    public function get_user(string $uuid = '') {
      if ($this -> check_uuid($uuid)) {
        $statement = $this -> prepare("
          SELECT `lastname`, `firstname`, `patronymic`,
          `group`, `payload`, `email`,
          `google_ldap_email`, `system_role`
          FROM `authorization` INNER JOIN `users_data`
          ON `authorization`.`id_data` = `users_data`.`id`
          WHERE `authorization`.`uuid` = ?;"
        );
        $statement -> bind_param('s', $this -> real_escape_string($uuid));
        $statement -> execute();
        return $statement -> get_result() -> fetch_assoc();
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

    // НАЧАЛО БЛОКА ФУНКЦИЙ СЕРВИСОВ

    public function list_of_services(int $id = 0) {
      $returned = [];
      $q = $id == 0 ? "SELECT `name`, `production`, `payload`, `groups` FROM `services`;" : "SELECT `name`, `production`, `payload`, `groups` FROM `services` WHERE `id` = {$id};";
      $list = $this -> query($q);
      if ($list -> num_rows != 0)
        while ($row = $list -> fetch_assoc())
          $returned[] = $row;
      return $returned;
    }
  }