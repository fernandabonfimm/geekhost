<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/Auth.php';

$auth = new Auth();
$auth->logout();
header('Location: /painel/login');
exit;
