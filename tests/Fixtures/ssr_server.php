<?php

declare(strict_types=1);

// Router script of the PHP built-in server standing in for the Inertia SSR server.

header('Content-Type: application/json');
header('Server: Inertia.js SSR');

if ($_SERVER['REQUEST_URI'] === '/health') {
	echo '{"status":"OK","timestamp":1}';
	return;
}

if ($_SERVER['REQUEST_URI'] !== '/render' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
	http_response_code(404);
	echo '{"status":"NOT_FOUND"}';
	return;
}

$body = file_get_contents('php://input');
$page = json_decode($body, true);

switch ($page['component']) {
	case 'Crash':
		http_response_code(500);
		echo json_encode([
			'error' => 'window is not defined',
			'type' => 'browser-api',
			'component' => 'Crash',
			'url' => '/crash',
			'browserApi' => 'The global window object',
			'hint' => 'Move the code to a lifecycle hook.',
			'stack' => 'ReferenceError: window is not defined',
			'sourceLocation' => 'resources/js/Pages/Crash.vue:14:3',
			'timestamp' => '2026-03-14T09:21:44.512Z',
		]);
		return;

	case 'Garbage':
		echo 'not json';
		return;

	case 'Slow':
		usleep(800000);
		echo '{"head":[],"body":"late"}';
		return;

	case 'Echo':
		echo json_encode(['head' => [], 'body' => $body]);
		return;

	default:
		echo json_encode([
			'head' => ["<title>{$page['component']}</title>", '<meta name="description" content="SSR">'],
			'body' => '<script data-page="app" type="application/json">' . json_encode($page) . '</script><div data-server-rendered="true" id="app">' . htmlspecialchars($page['props']['name'] ?? '') . '</div>',
		]);
}
