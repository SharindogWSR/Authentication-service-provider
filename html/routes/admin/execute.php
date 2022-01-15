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
          $_POST['admin_patronymic'] = empty($_POST['admin_patronymic']) ? '' : $_POST['admin_patronymic'];
          $_POST['admin_password'] = password_hash($_POST['admin_password'], PASSWORD_DEFAULT);
          $uuid = system::create_UUID();
          file_put_contents(__DIR__ . '/../../assets/php/dbase_connect.php', "<?php\n\t\$dbase_connect = [\n\t\t'hostname' => '{$_POST['database_hostname']}',\n\t\t'username' => '{$_POST['database_login']}',\n\t\t'password' => '{$_POST['database_password']}',\n\t\t'database' => '{$_POST['database_dbasename']}',\n\t];");
          $sql = new mysqli(
            $_POST['database_hostname'],
            $_POST['database_login'],
            $_POST['database_password'],
            $_POST['database_dbasename']
          );
          $sql -> multi_query(file_get_contents(__DIR__ . '/../../assets/sql/install.sql'));
          while (mysqli_next_result($sql));
          $sql -> query("INSERT INTO `users_data` (`lastname`, `firstname`, `patronymic`, `group`, `payload`) VALUES ('{$_POST['admin_lastname']}', '{$_POST['admin_firstname']}', '{$_POST['admin_patronymic']}', 'system', NULL);");
          $sql -> query("INSERT INTO `authorization` (`uuid`, `email`, `google_ldap_email`, `system_role`, `password_hash`, `id_data`) VALUES ('{$uuid}', '{$_POST['admin_email']}', NULL, 0, '{$_POST['admin_password']}', {$sql -> insert_id});");
          $privateRaw = openssl_pkey_new();
          $public = openssl_pkey_get_details($privateRaw)['key'];
          $private = '';
          openssl_pkey_export($privateRaw, $private);
          file_put_contents(__DIR__ . '/../../assets/php/keys.php', "<?php\n\t\$keys = [\n\t\t'public' => '{$public}',\n\t\t'private' => '{$private}'\n\t];");
          system::create_message('Успешеная установка! Система установлена в базу данных, ключи установлены.');
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