<?php
  /*
    Контроллер Router.php предназначен для обработки HTTP-путей.
    Все пути попадают в $_GET['path_controller'].
  */

  namespace nttek\controllers\router;

  class Pathfinder {
    private $directory = __DIR__ . '/../routers';
    private $finder = !empty($_GET['path_controller']) ? $_GET['path_controller'] : '';

    public function __construct() {
      if (!empty($this -> finder)) {
        if (file_exists($this -> get_path($this -> finder))) {
          require $this -> get_path($this -> finder);
        } else {
          $this -> show_code();
        }
      } else {
        require $this -> get_path('index');
      }
    }

    private function get_path(string $path) {
      return "{$this -> directory}/{$path}.php";
    }

    private function show_code(int $code = 404) {
      http_response_code($code);
      print(json_encode([
        'answer' => 'Смотри в HTTP Response Code.',
        'code' => $code,
      ]));
    }
  }
