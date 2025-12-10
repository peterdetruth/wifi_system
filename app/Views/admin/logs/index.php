<?= $this->extend('layouts/admin_layout') ?>
<?= $this->section('content') ?>

<h3 class="mb-4">System Logs</h3>
<?= view('templates/alerts') ?>

<!-- Filters -->
<form method="get" class="row g-3 mb-4">
    <div class="col-md-2">
        <input type="text" name="level" value="<?= esc($level ?? '') ?>" class="form-control" placeholder="Level">
    </div>
    <div class="col-md-2">
        <input type="text" name="type" value="<?= esc($type ?? '') ?>" class="form-control" placeholder="Type">
    </div>
    <div class="col-md-2">
        <input type="text" name="user_id" value="<?= esc($user_id ?? '') ?>" class="form-control" placeholder="User ID">
    </div>
    <div class="col-md-2">
        <input type="date" name="from" value="<?= esc($from ?? '') ?>" class="form-control" placeholder="From">
    </div>
    <div class="col-md-2">
        <input type="date" name="to" value="<?= esc($to ?? '') ?>" class="form-control" placeholder="To">
    </div>
    <div class="col-md-2">
        <button type="submit" class="btn btn-primary w-100">Filter</button>
    </div>
</form>

<!-- Logs Table -->
<div class="table-responsive">
    <table class="table table-striped table-bordered">
        <thead class="table-dark">
            <tr>
                <th>ID</th>
                <th>Level</th>
                <th>Type</th>
                <th>Message</th>
                <th>Context</th>
                <th>User ID</th>
                <th>IP Address</th>
                <th>Created At</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($logs)) : ?>
                <?php foreach ($logs as $log) : ?>
                    <tr>
                        <td><?= esc($log['id']) ?></td>
                        <td><?= esc($log['level']) ?></td>
                        <td><?= esc($log['type']) ?></td>
                        <td><?= esc($log['message']) ?></td>
                        <td><?= esc($log['context']) ?></td>
                        <td><?= esc($log['user_id']) ?></td>
                        <td><?= esc($log['ip_address']) ?></td>
                        <td><?= esc($log['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr>
                    <td colspan="8" class="text-center">No logs found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Pagination -->
<?php if ($pager && $pager->getPageCount('logs') > 1) : ?>
<style>
    /* Pagination styling */
    .pagination {
        display: flex;
        justify-content: center;
        margin-top: 20px;
        gap: 4px; /* spacing between items */
        flex-wrap: wrap;
    }
    .pagination li.page-item a,
    .pagination li.page-item span {
        color: #0d6efd;
        padding: 6px 12px;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        text-decoration: none;
        margin: 0 2px; /* horizontal spacing */
    }
    .pagination li.page-item.active span {
        background-color: #0d6efd;
        color: white;
        border-color: #0d6efd;
    }
    .pagination li.page-item.disabled span {
        color: #6c757d;
    }
</style>
<nav aria-label="Page navigation">
    <?= $pager->links('logs', 'default_full') ?>
</nav>
<?php endif; ?>

<?= $this->endSection() ?>
