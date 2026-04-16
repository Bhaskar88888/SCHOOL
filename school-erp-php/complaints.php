<?php
require_once __DIR__ . '/includes/auth.php';
require_auth();

header('Location: ' . BASE_URL . '/communication.php');
exit;
