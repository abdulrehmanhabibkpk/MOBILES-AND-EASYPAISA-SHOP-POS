<?php
require_once __DIR__ . '/backend/config.php';
logoutUserSession($pdo);
header("Location: login/index.php");
exit();
