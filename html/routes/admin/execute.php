<?php
  require __DIR__ . '/../../controllers/autoload.php';

  if (system::check_method()) {
    $database;
    try {
      $database = new connect();
      system::create_message('Система уже настроена!', [], 403);
      $database -> close();
    } catch (Exception $e) {
      $check_payload = system::check_required_payload([
        'database_login',
        'database_password',
        'database_hostname',
        'database_dbasename',
        'admin_email',
        'admin_password',
        'admin_firstname',
        'admin_lastname'
      ]);
      if (empty($check_payload)) {
        $check_connection = connect::check_connect(
          $_POST['database_hostname'],
          $_POST['database_login'],
          $_POST['database_password'],
          $_POST['database_dbasename']
        );
        if (gettype($check_connection) == 'object') {
          file_put_contents(__DIR__ . '/../../assets/php/dbase_connect.php', "<?php\n\t\$dbase_connect = [\n\t\t'hostname' => '{$_POST['database_hostname']}',\n\t\t'username' => '{$_POST['database_login']}',\n\t\t'password' => '{$_POST['database_password']}',\n\t\t'database' => '{$_POST['database_dbasename']}',\n\t];");
          
        } else system::create_message(
          'Некорректное подключение к базе данных!',
          [],
          401
        );
      } else system::create_message(
          'Не хватает некоторых данных!',
          [
            'not_transferred' => $check_payload
          ],
          400
        );
    }
  } else system::create_message('Неподдерживаемый метод! Поддерживаемые методы: POST.', [], 405);