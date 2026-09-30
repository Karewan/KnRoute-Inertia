<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use Karewan\KnRouteInertia\Inertia;
use Karewan\KnRouteInertia\NativeSessionStore;
use Tests\Fixtures\MemorySessionHandler;
use Tests\Response;

// Sessions cannot start once output was sent: the results are buffered until the end.
ob_start();

ini_set('session.use_cookies', '0');
ini_set('session.use_strict_mode', '0');
ini_set('session.cache_limiter', '');
session_set_save_handler(new MemorySessionHandler(), true);

test('A read never creates a session', function (): void {
	$store = new NativeSessionStore();
	assertSame(null, $store->pull('_inertia'), 'nothing to read');
	assertSame(PHP_SESSION_NONE, session_status(), 'no session started');
});

test('A write starts the session with the starter of the application', function (): void {
	$started = 0;
	$store = new NativeSessionStore(static function () use (&$started): void {
		$started++;
		session_id('client-session');
		session_start();
	});
	$store->put('_inertia', ['flash' => ['message' => 'saved']]);

	assertSame(1, $started, 'starter called');
	assertSame(['flash' => ['message' => 'saved']], $_SESSION['_inertia'], 'stored in $_SESSION');
	session_write_close();
});

test('A read resumes the session of a client that has its cookie', function (): void {
	$_COOKIE[session_name()] = 'client-session';
	session_id('client-session');
	$store = new NativeSessionStore();

	assertSame(['flash' => ['message' => 'saved']], $store->pull('_inertia'), 'read');
	assertSame(PHP_SESSION_ACTIVE, session_status(), 'session resumed');
	assertSame(null, $store->pull('_inertia'), 'removed');
});

test('The adapter uses $_SESSION by default', function (): void {
	Inertia::configure(rootView: static fn($view): string => '', version: VERSION);

	request('POST', '/users', inertia());
	Inertia::flash('message', 'User created');
	Inertia::redirect('/users');
	assertSame(['flash' => ['message' => 'User created']], $_SESSION[Inertia::SESSION_KEY], 'kept in the session');

	request('GET', '/users', inertia());
	assertSame(['message' => 'User created'], Inertia::page('Users/Index')['flash'], 'sent with the next page');
	assertTrue(!isset($_SESSION[Inertia::SESSION_KEY]), 'consumed');
});

test('Without session, the redirect data is dropped', function (): void {
	Inertia::configure(rootView: static fn($view): string => '', version: VERSION, session: false);
	$_SESSION = [];
	request('POST', '/users');
	Inertia::flash('message', 'lost');
	Inertia::redirect('/users');
	assertSame([], $_SESSION, 'nothing stored');
});

finish();
