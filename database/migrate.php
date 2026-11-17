<?php

declare(strict_types=1);

/**
 * CLI: php database/migrate.php
 * Creates/updates checkout tables using the configured DB_DRIVER.
 */
require dirname(__DIR__) . '/public_html/includes/env.php';
require dirname(__DIR__) . '/public_html/includes/db.php';

geekhost_load_env(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');
$pdo = geekhost_db();
echo 'OK: migrated with driver ' . $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) . PHP_EOL;
