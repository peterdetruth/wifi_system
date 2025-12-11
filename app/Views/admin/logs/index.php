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

<!-- Styles -->
<style>
    .log-badge {
        padding: 4px 8px;
        border-radius: 4px;
        font-weight: 600;
        color: #fff;
    }

    .log-error {
        background: #dc3545;
    }

    .log-warning {
        background: #ffc107;
        color: #000 !important;
    }

    .log-info {
        background: #0d6efd;
    }

    .log-debug {
        background: #6c757d;
    }

    .expandable-text {
        max-width: 250px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        cursor: pointer;
    }

    .context-box {
        white-space: pre-wrap;
        font-family: monospace;
        font-size: 13px;
        background: #f8f9fa;
        padding: 10px;
        border-radius: 6px;
        border: 1px solid #ddd;
    }

    .sortable:hover {
        cursor: pointer;
        text-decoration: underline;
    }

    .pagination {
        display: flex;
        justify-content: center;
        margin-top: 20px;
        gap: 6px;
        flex-wrap: wrap;
    }
</style>

<!-- Logs Table -->
<div class="table-responsive">
    <table class="table table-striped table-bordered align-middle">
        <thead class="table-dark">
            <tr>
                <th class="sortable" onclick="sortTable('id')">ID</th>
                <th class="sortable" onclick="sortTable('level')">Level</th>
                <th class="sortable" onclick="sortTable('type')">Type</th>
                <th>Message</th>
                <th>Context</th>
                <th class="sortable" onclick="sortTable('user_id')">User ID</th>
                <th>IP Address</th>
                <th class="sortable" onclick="sortTable('created_at')">Created At</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($logs)) : ?>
                <?php foreach ($logs as $log) : ?>

                    <?php
                    $levelClass = [
                        'error' => 'log-error',
                        'warning' => 'log-warning',
                        'info' => 'log-info',
                        'debug' => 'log-debug'
                    ][$log['level']] ?? 'log-info';

                    $prettyContext = $log['context'];
                    if (!empty($log['context']) && is_string($log['context'])) {
                        $decoded = json_decode($log['context'], true);
                        if ($decoded !== null) {
                            $prettyContext = json_encode($decoded, JSON_PRETTY_PRINT);
                        }
                    }
                    ?>

                    <tr>
                        <td><?= esc($log['id']) ?></td>

                        <td>
                            <span class="log-badge <?= $levelClass ?>">
                                <?= strtoupper(esc($log['level'])) ?>
                            </span>
                        </td>

                        <td><?= esc($log['type']) ?></td>

                        <!-- Expandable message -->
                        <td>
                            <div class="expandable-text" onclick="toggleExpand(this)">
                                <?= esc($log['message']) ?>
                            </div>
                        </td>

                        <!-- Pretty JSON context -->
                        <td>
                            <?php if (!empty($prettyContext)) : ?>
                                <div class="context-box"><?= esc($prettyContext) ?></div>
                            <?php else : ?>
                                <em class="text-muted">None</em>
                            <?php endif; ?>
                        </td>

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
    <nav aria-label="Page navigation">
        <?= $pager->links('logs', 'default_full') ?>
    </nav>
<?php endif; ?>

<script>
    function toggleExpand(el) {
        if (el.style.whiteSpace === "normal") {
            el.style.whiteSpace = "nowrap";
            el.style.overflow = "hidden";
            el.style.textOverflow = "ellipsis";
        } else {
            el.style.whiteSpace = "normal";
            el.style.overflow = "visible";
            el.style.textOverflow = "unset";
        }
    }

    function sortTable(column) {
        const params = new URLSearchParams(window.location.search);
        const currentSort = params.get("sort");
        const currentDir = params.get("dir") || "asc";

        if (currentSort === column) {
            params.set("dir", currentDir === "asc" ? "desc" : "asc");
        } else {
            params.set("sort", column);
            params.set("dir", "asc");
        }

        window.location.search = params.toString();
    }
</script>

<?= $this->endSection() ?>