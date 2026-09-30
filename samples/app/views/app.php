<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?= \Samples\Vite::tags('resources/js/app.js') ?>
	<?= $inertia->head('<title>KnRoute Inertia</title>') ?>
</head>
<body>
	<?= $inertia->app() ?>
</body>
</html>
