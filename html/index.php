<?php
  header("Content-Type: application/json");

  require __DIR__ . '/controllers/router.php';

  new Pathfinder();