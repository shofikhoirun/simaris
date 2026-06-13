<?php
require_once __DIR__ . '/includes/functions.php';
if (isLoggedIn()) {
    logAudit('LOGOUT', 'users', currentUser()['id'], 'User logout');
}
logout();
