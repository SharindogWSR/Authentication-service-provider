<?php
  require __DIR__ . '/../../controllers/autoload.php';

  $database;
  $system_enabled = false;

  try {
    $database = new connect();
    $system_enabled = true;
  } catch (ErrorException $e) {
    system::create_message(
      'Система ещё не настроена!',
      [
        'redirect' => '/admin/execute',
      ],
      403);
  }

  if ($system_enabled) {
    if (system::check_method()) {

    } else
      system::create_message(
        'Неподдерживаемый метод! Поддерживаемые методы: POST.',
        [],
        405
      );
  }

  $database -> close();