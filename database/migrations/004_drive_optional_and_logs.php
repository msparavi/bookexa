<?php
declare(strict_types=1);

return [
    'description' => 'Make items.drive_link optional for direct local page-preparation workflow',
    'up' => static function (PDO $pdo): void {
        mig_modify_column_if_exists($pdo, 'items', 'drive_link', 'TEXT DEFAULT NULL');
    },
];
