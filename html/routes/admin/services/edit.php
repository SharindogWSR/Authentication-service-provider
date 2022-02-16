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
                'token'
              ]);
              if (empty($check_payload)) {
                $check_payload = [];
                foreach ($_POST as $key => $value)
                  if (
                    in_array($key, ['production', 'payload', 'can_edit_user', 'can_get_list_of_services']) &&
                    !in_array($value, ['0', '1'])
                  )
                    $check_payload[] = $key;
                if (empty($check_payload)) {
                  $check_payload = false;
                  if (empty($_POST['groups']))
                    $check_payload = true;
                  else {
                    $_POST['groups'] = json_decode($_POST['groups']);
                    if (!is_null($_POST['groups']))
                      if (!empty($_POST['groups'] -> default) && !empty($_POST['groups'] -> list))
                        if (is_int($_POST['groups'] -> default) && in_array($_POST['groups'] -> default, $_POST['groups'] -> list)) {
                          $check = [];
                          foreach ($_POST['groups'] -> list as $value)
                            if (!is_int($value))
                              $check[] = $value;
                          if (empty($check))
                            $check_payload = true;
                        }
                  }
                  if ($check_payload) {
                    $_POST['groups'] = json_encode($_POST['groups']);
                    if ($database -> edit_service($_POST))
                      system::create_message('Сервис успешно изменен!');
                    else system::create_message('Невозможно изменить сервис! Возможно, что такое имя уже занято.', [], 500);
                  } else system::create_message('Проблема с группами!', [], 400);
                } else system::create_message(
                  'Некоторые триггеры получили некорректные значения!',
                  [
                    'addition' => $check_payload
                  ],
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