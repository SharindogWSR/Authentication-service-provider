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
      if (!empty(getallheaders()['Authorization'])) {
        $token = getallheaders()['Authorization'];
        if (stripos($token, 'Bearer ') !== false) {
          $token = explode(' ', $token)[1];
          $token = $tokens -> decode_jwt_token($token);
          if ($token[0]) {
            if ($token[1] -> user_data -> group == 'system') {
              $check_payload = system::check_required_payload([
                'email',
                'lastname',
                'firstname',
                'group'
              ]);
              if (empty($check_payload)) {
                if (
                  stripos($_POST['group'], 'students') !== false ||
                  in_array($_POST['group'], ['system', 'teachers', 'users'])
                ) {
                  if ($new_auth = $database -> register_user(
                    $_POST['email'],
                    $_POST['lastname'],
                    $_POST['firstname'],
                    $_POST['patronymic'],
                    $_POST['group']
                  )) system::create_message(
                      'Пользователь успешно зарегестрирован!',
                      $new_auth
                    );
                  else system::create_message(
                    'Нет возможности зарегестрировать пользователя. Возможно, что переданный адрес электронной почты уже занят.',
                    [],
                    400
                  );
                } else system::create_message(
                  'Некорректно составлен group.',
                  [],
                  400
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