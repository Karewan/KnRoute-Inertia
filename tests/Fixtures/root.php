<!DOCTYPE html>
<html>
<head>
<?= $inertia->head('<title>Fallback</title>') ?>
<meta name="description" content="<?= htmlspecialchars($description ?? '', ENT_QUOTES) ?>">
</head>
<body data-component="<?= htmlspecialchars($page['component']) ?>">
<?= $inertia->app() ?>
</body>
</html>
