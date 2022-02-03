<?php
  require __DIR__ . '/../controllers/autoload.php';

  $t = new connect();
  var_dump($t -> edit_service([
    'token' => 'a9e948aba10a02a4d5d6b59f9fd39127a8782f706187be88cafdcd77c8365b71d3999e416e4f571abafab621e6c26824a33dd5677141f55d2186422655e9bdaf',
    'name' => 'Test...',
    'can_get_list_of_services' => 0
  ]));
  $t -> close();