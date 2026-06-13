<?php
require_once __DIR__ . '/includes/functions.php';
header('Location: ' . (isLoggedIn() ? 'dashboard.php' : 'login.php'));
exit;
