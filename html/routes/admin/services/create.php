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
                'name',
                'production',
                'payload',
                'can_edit_user',
                'can_get_list_of_services',
                'groups'
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
                  $groups = json_decode($_POST['groups']);
                  if (!is_null($groups)) {
                    if (!empty($groups -> default) && !empty($groups -> list)) {
                      if (!is_int($groups -> default) && in_array($groups -> default, $groups -> list)) {
                        $check = [];
                        foreach ($groups -> list as $value)
                          if (!is_int($value))
                            $check[] = $value;
                        if (!empty($check)) {
                          $token = hash('SHA512', $_POST['name'] . time());
                          if ($database -> create_service(
                            $_POST['name'],
                            $token,
                            $_POST['production'] == '1',
                            $_POST['payload'] == '1',
                            $_POST['can_edit_user'] == '1',
                            $_POST['can_get_list_of_services'] == '1',
                            $groups
                          )) {
                            system::create_message(
                              'Сервис успешно создан!',
                              [
                                'token' => $token,
                              ]
                            );
                          } else system::create_message('Невозможно создать сервис! Возможно, что такое имя уже занято.', [], 500);
                        } else system::create_message(
                          'Некорректные значения list. Все значения должны быть целочисленными.',
                          [
                            'list' => $check
                          ],
                          400
                        );
                      } else system::create_message('Значение default должно быть целочисленным и быть в значении list!', [], 400);
                    } else system::create_message('Некорректная схема groups!', [], 400);
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