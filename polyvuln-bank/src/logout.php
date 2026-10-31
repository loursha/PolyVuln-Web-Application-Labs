<?php
require_once __DIR__ . '/includes/auth.php';
$_SESSION = [];
setcookie('pv_auth', '', time() - 3600, '/');
redirect('/login.php');
