<?php if (!empty($logs)): ?>
    <table class="table table-striped table-sm">
        <thead>
            <tr>
                <th>ID</th>
                <th>Result</th>
                <th>Description</th>
                <th>Timestamp</th>
                <th>Raw Callback (JSON)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($logs as $log): ?>
                <?php
                    $json = json_decode($log['raw_callback'], true);
                    $stk = $json['Body']['stkCallback'] ?? [];
                ?>
                <tr>
                    <td><?= esc($log['id']) ?></td>
                    <td>
                        <?= esc($stk['ResultCode'] ?? '-') ?>
                        <?php if (($stk['ResultCode'] ?? 1) == 0): ?>
                            <span class="badge bg-success">✅ Success</span>
                        <?php else: ?>
                            <span class="badge bg-danger">❌ Failed</span>
                        <?php endif; ?>
                    </td>
                    <td><?= esc($stk['ResultDesc'] ?? '-') ?></td>
                    <td><?= esc($log['created_at']) ?></td>
                    <td>
                        <pre class="small bg-light p-2 rounded"><?= esc(json_encode($json, JSON_PRETTY_PRINT)) ?></pre>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <div class="alert alert-info">No M-PESA logs yet.</div>
<?php endif; ?>
