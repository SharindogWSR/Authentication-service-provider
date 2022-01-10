<?php
  namespace nttek;

  header("Content-Type: application/json");

  require __DIR__ . '/controllers/router.php';

  new \nttek\controllers\router\Pathfinder();
