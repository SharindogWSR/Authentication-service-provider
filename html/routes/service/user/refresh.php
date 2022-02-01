<?php
  require __DIR__ . '/../../../controllers/autoload.php';
  if (system::check_method(['GET'])) {
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
      $checkable = ['refresh_token', 'service_token'];
      if (!empty($_GET['is_admin']))
        if ($_GET['is_admin'] == '1')
          $checkable = ['refresh_token'];
      $check_payload = system::check_required_payload(
        $checkable,
        'GET'
      );
      if (empty($check_payload)) {
        if (!empty(getallheaders()['User-Agent'])) {
          $user_agent = getallheaders()['User-Agent'];
          if (in_array('service_token', $checkable)) {
            $services = $database -> list_of_services($_GET['service_token']);
            if (!empty($services)) {
              $services = $services[0];
              $check_refresh_token = $database -> check_refresh_token(
                $_GET['refresh_token'],
                $user_agent
              );
              switch ($check_refresh_token) {
                case 0:
                  system::create_message('Внутренняя ошибка сервиса!', [], 500);
                break;
                case 1:
                case 2:
                  system::create_message(
                    'Проблема при авторизации. Авторизуйтесь ещё раз!',
                    [
                      'error_type' => $check_refresh_token
                    ],
                    401
                  );
                break;
                default:
                  if ($check_refresh_token['service'] == $services['name']) {
                    $jwt = $tokens -> create_jwt_token(
                      $database -> get_user($check_refresh_token['uuid']),
                      $check_refresh_token['uuid'],
                      $check_refresh_token['service']
                    );
                    $refresh = $tokens -> create_refresh_token($jwt);
                    $database -> save_refresh_token(
                      $check_refresh_token['uuid'],
                      $refresh,
                      $user_agent,
                      $serivces['id']);
                    system::create_message(
                      'Успешная авторизация!',
                      [
                        'jwt' => $jwt,
                        'refresh' => $refresh
                      ]
                    );
                  } else system::create_message('Проблема при авторизации. Авторизуйтесь ещё раз!', [], 403);
                break;
              }
            } else system::create_message('Сервис не найден!', [], 401);
          } else {
            $check_refresh_token = $database -> check_refresh_token(
              $_GET['refresh_token'],
              $user_agent
            );
            switch ($check_refresh_token) {
              case 0:
                system::create_message('Внутренняя ошибка сервиса!', [], 500);
              break;
              case 1:
              case 2:
                system::create_message(
                  'Проблема при авторизации. Авторизуйтесь ещё раз!',
                  [
                    'error_type' => $check_refresh_token
                  ],
                  401
                );
              break;
              default:
                if ($check_refresh_token['service'] == 'authorization') {
                  $jwt = $tokens -> create_jwt_token(
                    $database -> get_user($check_refresh_token['uuid']),
                    $check_refresh_token['uuid'],
                    $check_refresh_token['service']
                  );
                  $refresh = $tokens -> create_refresh_token($jwt);
                  $database -> save_refresh_token(
                    $check_refresh_token['uuid'],
                    $refresh,
                    $user_agent,
                    0);
                  system::create_message(
                    'Успешная авторизация!',
                    [
                      'jwt' => $jwt,
                      'refresh' => $refresh
                    ]
                  );
                } else system::create_message('Проблема при авторизации. Авторизуйтесь ещё раз!', [], 403);
              break;
            }
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
  } else system::create_message('Неподдерживаемый метод! Поддерживаемые методы: GET.', [], 405);