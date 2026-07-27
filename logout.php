<?php
require_once __DIR__ . '/config/app.php';
logout();
header('Location: ' . APP_URL . '/login.php');
exit;
