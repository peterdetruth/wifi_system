<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Database Schema Check</title>
<style>
body { font-family: Arial, sans-serif; background: #f4f4f4; padding: 20px; }
table { border-collapse: collapse; width: 100%; margin-bottom: 30px; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
th { background-color: #333; color: white; }
td.missing { background-color: #ffcccc; }
td.ok { background-color: #ccffcc; }
</style>
</head>
<body>
<h1>Database Schema Verification</h1>

<?php foreach($results as $table => $columns): ?>
<h2><?= $table ?></h2>
<table>
<tr>
<th>Column Name</th>
<th>Status</th>
</tr>
<?php foreach($columns as $col => $exists): ?>
<tr>
<td><?= $col ?></td>
<td class="<?= $exists ? 'ok' : 'missing' ?>">
<?= $exists ? 'Exists' : 'Missing' ?>
</td>
</tr>
<?php endforeach; ?>
</table>
<?php endforeach; ?>

</body>
</html>
