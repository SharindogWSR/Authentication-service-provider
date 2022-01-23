<?php
  require __DIR__ . '/../../../controllers/autoload.php';
  if (system::check_method()) {
    $database;
    $system_is_ready = false;
    try {
      $database = new connect();
      $system_is_ready = true;
    } catch (ErrorException $e) {
      system::create_message('Ошибка подключения к базе данных!', [], 503);
    }
    if ($system_is_ready) {
      $check_payload = system::check_required_payload(
        [
          'login',
          'password'
        ]
      );
      if (empty($check_payload)) {
        // Основной код здесь
      } else system::create_message(
        'Не хватает некоторых данных!',
        [
          'not_transferred' => $check_payload
        ],
        400
      );
      $database -> close();
    }
  } else system::create_message('Неподдерживаемый метод! Поддерживаемые методы: POST.', [], 405);