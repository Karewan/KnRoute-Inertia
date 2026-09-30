<?php

declare(strict_types=1);

use Karewan\KnRoute\Router;
use Karewan\KnRouteInertia\ErrorHandler;
use Karewan\KnRouteInertia\Inertia;
use Karewan\KnRouteInertia\InertiaMiddleware;
use Karewan\KnRouteInertia\Ssr\HttpSsrGateway;
use Samples\Vite;

// PHP built-in server: the built assets are plain files.
if (PHP_SAPI === 'cli-server' && is_file(__DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))) return false;

require __DIR__ . '/../../vendor/autoload.php';

spl_autoload_register(static function (string $class): void {
	if (str_starts_with($class, 'Samples\\')) require __DIR__ . '/../app/' . str_replace('\\', '/', substr($class, 8)) . '.php';
});

session_start();

// VITE_DEV_SERVER=http://localhost:5173 serves the assets (and renders the pages) with the Vite development server.
$devServer = getenv('VITE_DEV_SERVER') ?: null;
Vite::configure(__DIR__ . '/build', $devServer);

// INERTIA_SSR=1 renders the pages on the server: the Vite endpoint in development, the Node server of the
// SSR bundle (node ssr/app.js) otherwise.
$ssr = getenv('INERTIA_SSR')
	? new HttpSsrGateway($devServer !== null ? "{$devServer}/__inertia_ssr" : 'http://127.0.0.1:13714/render')
	: null;

Inertia::configure(
	rootView: __DIR__ . '/../app/views/app.php',
	version: Vite::version(...),
	share: static fn(): array => [
		'appName' => 'KnRoute Inertia',
		'auth' => ['user' => ['name' => 'Demo user']],
	],
	ssr: $ssr,
);

$router = new Router();
$router->addGlobalMiddleware(new InertiaMiddleware());
$router->setDefaultErrorHandler(new ErrorHandler('Error'));
$router->registerRoutesFromControllers(
	controllersPath: __DIR__ . '/../app/Controllers',
	cacheFile: sys_get_temp_dir() . '/knroute-inertia-sample-routes.php',
	scanForModifiedControllers: true,
);
$router->run();
