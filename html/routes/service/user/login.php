<?php
  require __DIR__ . '/../../../assets/php/configuration.php';
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
      $checkable = ['email', 'password', 'service_token'];
      if (!empty($_POST['is_admin']))
        if ($_POST['is_admin'] == '1')
          $checkable = ['email', 'password'];
      $check_payload = system::check_required_payload($checkable);
      if (empty($check_payload)) {
        if (!empty(getallheaders()['User-Agent'])) {
          $user_agent = getallheaders()['User-Agent'];
          if (in_array('service_token', $checkable)) {
            $services = $database -> list_of_services($_POST['service_token']);
            if (!empty($services)) {
              $services = $services[0];
              if ($uuid = $database -> user_login(
                $_POST['email'],
                $_POST['password'],
                $user_agent,
                system::get_ip_address(),
                $services['id']
              )) {
                $jwt = $tokens -> create_jwt_token($database -> get_user($uuid), $uuid, $services['name']);
                $refresh = $tokens -> create_refresh_token($jwt);
                $database -> save_refresh_token($uuid, $refresh, $user_agent, $services['id']);
                system::create_message(
                  'Успешная авторизация!',
                  [
                    'jwt' => $jwt,
                    'refresh' => $refresh
                  ]
                );
              } else system::create_message('Некорректные данные для авторизации!', [], 401);
            } else system::create_message('Некорректные данные для авторизации!', [], 401);
          } else {
            if ($uuid = $database -> user_login($_POST['email'], $_POST['password'], $user_agent, system::get_ip_address(), 0)) {
              $user = $database -> get_user($uuid);
              if ($user['group'] == 'system') {
                $jwt = $tokens -> create_jwt_token($user, $uuid);
                $refresh = $tokens -> create_refresh_token($jwt);
                $database -> save_refresh_token($uuid, $refresh, $user_agent);
                system::create_message(
                  'Успешная авторизация!',
                  [
                    'jwt' => $jwt,
                    'refresh' => $refresh
                  ]
                );
              } else system::create_message('Некорректные данные для авторизации!', [], 401);
            } else system::create_message('Некорректные данные для авторизации!', [], 401);
          }
        } else system::create_message('В запросе не установлен User-Agent.', [], 400);
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