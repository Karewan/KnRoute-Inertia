<?php

declare(strict_types=1);

// Router script of the PHP built-in server: echoes the data read by Inertia::input().

require __DIR__ . '/../../vendor/autoload.php';

header('Content-Type: application/json');
echo json_encode(Karewan\KnRouteInertia\Inertia::input());
