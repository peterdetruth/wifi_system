<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Database Schema Verification (Detailed)</title>
<style>
body { font-family: Arial, sans-serif; padding: 20px; background: #f4f4f4; }
h1, h2 { font-family: sans-serif; }
table { border-collapse: collapse; width: 100%; margin-bottom: 40px; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
th { background-color: #333; color: white; }
td.ok { background-color: #ccffcc; }
td.missing { background-color: #ffcccc; }
td.mismatch { background-color: #ffeb99; }
</style>
</head>
<body>
<h1>Database Schema Verification (Detailed)</h1>

<?php foreach($results as $table => $columns): ?>
<h2><?= $table ?></h2>
<table>
<tr>
<th>Column</th>
<th>Status</th>
<th>Expected Type</th>
<th>Current Type</th>
<th>Expected Nullable</th>
<th>Current Nullable</th>
<th>Current Default</th>
</tr>
<?php foreach($columns as $colName => $col): ?>
<tr>
<td><?= $colName ?></td>
<td class="<?= $col['exists'] ? ($col['matches_type'] && $col['matches_nullable'] ? 'ok' : 'mismatch') : 'missing' ?>">
<?= $col['exists'] ? ($col['matches_type'] && $col['matches_nullable'] ? 'Exists' : 'Mismatch') : 'Missing' ?>
</td>
<td><?= $col['type'] ?></td>
<td><?= $col['current_type'] ?? '-' ?></td>
<td><?= $col['nullable'] ?></td>
<td><?= $col['current_nullable'] ?? '-' ?></td>
<td><?= $col['current_default'] ?? '-' ?></td>
</tr>
<?php endforeach; ?>
</table>
<?php endforeach; ?>

</body>
</html>
