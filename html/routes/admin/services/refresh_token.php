<?php
  require __DIR__ . '/../../../controllers/autoload.php';
  if (system::check_method()) {
    $database;
    $tokens;
    $system_is_ready = false;
    try {
      $database = new connect();
      $tokens = new tokens();
      $system_is_ready = true;
    } catch (ErrorException $e) {
      system::create_message('Ошибка подключения к базе данных!', [], 503);
    }
    if ($system_is_ready) {
      $token = getallheaders()['Authorization'];
      if (!empty($token)) {
        if (stripos($token, 'Bearer ') !== false) {
          $token = explode(' ', $token)[1];
          $token = $tokens -> decode_jwt_token($token);
          if ($token[0]) {
            if ($token[1] -> user_data -> group == 'system') {
              $check_payload = system::check_required_payload([
                'token'
              ]);
              if (empty($check_payload)) {
                if ($new_token = $database -> get_new_refresh_token_for_service($_POST['token'])) {
                  system::create_message(
                    'Выдан новый токен.',
                    [
                      'refresh' => $new_token
                    ]
                  );
                } else system::create_message(
                  'Произошла проблема при обновлении Refresh Token. Возможно, что такого сервиса не существует.',
                  [],
                  500
                );
              } else system::create_message(
                'Не хватает некоторых данных!',
                [
                  'not_transferred' => $check_payload
                ],
                400
              );
            } else system::create_message('Недостаточно прав для совершения данного действия!', [], 403);
          } else system::create_message(
            'Проблема с авторизацией',
            [
              'addition' => $token[1],
            ],
            401
          );
        } else system::create_message('Требуется Bearer-представление!', [], 401);
      } else system::create_message('Не предоставлены данные для идентификации!', [], 401);
      $database -> close();
    }
  } else system::create_message('Неподдерживаемый метод! Поддерживаемые методы: POST.', [], 405);