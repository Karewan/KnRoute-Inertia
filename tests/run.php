<?php

declare(strict_types=1);

// Every test file runs in its own PHP process: the adapter and the router keep static state.
$files = glob(__DIR__ . '/cases/*.php') ?: [];
sort($files, SORT_STRING);

$failedFiles = 0;
$passed = $failed = 0;

foreach ($files as $file) {
	echo "\n" . basename($file, '.php') . "\n";

	$process = proc_open([PHP_BINARY, $file], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	if (!is_resource($process)) {
		echo "FAIL  could not start the test process\n";
		$failedFiles++;
		continue;
	}

	$stdout = stream_get_contents($pipes[1]);
	$stderr = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	$exitCode = proc_close($process);

	echo $stdout;
	if ($stderr !== '') echo $stderr;

	$passed += substr_count($stdout, "PASS  ");
	$failed += substr_count($stdout, "FAIL  ");
	if ($exitCode !== 0) $failedFiles++;
}

echo "\n{$passed} passed, {$failed} failed\n";
exit($failedFiles > 0 || $failed > 0 ? 1 : 0);
