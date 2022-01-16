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

    public function check_uuid(string $uuid = '') {
      if (!empty($uuid)) {
        
      } else return false;
    }

    private function prepared_select(string $table = '') {

    }
  }