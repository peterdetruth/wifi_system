<!DOCTYPE html>
<html>
<head>
    <title>Feature Requests</title>
    <link rel="stylesheet" href="<?= base_url('assets/css/bootstrap.min.css') ?>">
</head>
<body class="p-4">

<h2>Feature Requests</h2>

<a href="<?= base_url('admin/features/create') ?>" class="btn btn-primary mb-3">+ Add New Feature</a>

<?php if (session()->getFlashdata('success')): ?>
    <div class="alert alert-success"><?= session()->getFlashdata('success') ?></div>
<?php endif; ?>

<table class="table table-bordered">
    <thead>
        <tr>
            <th>#</th>
            <th>Feature</th>
            <th>Description</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
    </thead>

    <tbody>
        <?php foreach ($features as $f): ?>
        <tr>
            <td><?= $f['id'] ?></td>
            <td><?= esc($f['name']) ?></td>
            <td><?= esc($f['description']) ?></td>
            <td>
                <?php if ($f['status'] == 'pending'): ?>
                    <span class="badge bg-warning">Pending</span>
                <?php else: ?>
                    <span class="badge bg-success">Completed</span>
                <?php endif; ?>
            </td>

            <td>
                <?php if ($f['status'] == 'pending'): ?>
                    <a href="<?= base_url('admin/features/complete/'.$f['id']) ?>" 
                       class="btn btn-success btn-sm">Mark Complete</a>
                <?php endif; ?>

                <a href="<?= base_url('admin/features/delete/'.$f['id']) ?>" 
                   class="btn btn-danger btn-sm"
                   onclick="return confirm('Delete this feature?')">Delete</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

</body>
</html>
