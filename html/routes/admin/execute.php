<?php
  require __DIR__ . '/../../controllers/autoload.php';

  if (system::check_method()) {
    $database;
    try {
      $database = new connect();
      http_response_code(403);
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
        system::create_message('Проверка', [
          'mysqli_answer' => connect::check_connect(
            $_POST['database_hostname'],
            $_POST['database_login'],
            $_POST['database_password'],
            $_POST['database_dbasename']
          ),
        ]);
      } else {

      }
      if (empty($check_payload)) {
      } else system::create_message(
        'Не хватает некоторых данных!',
        [
          'not_transferred' => $check_payload
        ],
        400
    );
    }
  } else system::create_message('Неподдерживаемый метод! Поддерживаемые методы: POST.', [], 405);