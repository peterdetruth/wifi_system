<!DOCTYPE html>
<html>
<head>
    <title>Add Feature</title>
    <link rel="stylesheet" href="<?= base_url('assets/css/bootstrap.min.css') ?>">
</head>
<body class="p-4">

<h2>Add New Feature</h2>

<form method="post" action="<?= base_url('admin/features/store') ?>" class="mt-3">
    <div class="mb-3">
        <label>Feature Name</label>
        <input type="text" name="name" class="form-control" required>
    </div>

    <div class="mb-3">
        <label>Description</label>
        <textarea name="description" class="form-control" rows="5"></textarea>
    </div>

    <button class="btn btn-primary">Save Feature</button>
    <a href="<?= base_url('admin/features') ?>" class="btn btn-secondary">Cancel</a>
</form>

</body>
</html>
