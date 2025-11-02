<?= $this->extend('layouts/admin_layout') ?>

<?= $this->section('content') ?>
<div class="container-fluid mt-4">

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h4 class="fw-bold mb-0">📜 M-PESA Callback Logs</h4>

        <div class="d-flex align-items-center gap-2">
            <select id="filter-result" class="form-select form-select-sm" style="width:auto;">
                <option value="all" selected>Show All</option>
                <option value="success">✅ Success Only</option>
                <option value="failed">⚠️ Failed Only</option>
            </select>

            <button id="toggle-live" class="btn btn-success btn-sm">
                <i class="bi bi-play-circle"></i> Live Mode: ON
            </button>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-3">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="logs-table">
                    <thead class="table-dark">
                        <tr>
                            <th style="width: 5%">#</th>
                            <th style="width: 15%">Received At</th>
                            <th>Result</th>
                            <th>Callback Data (JSON)</th>
                        </tr>
                    </thead>
                    <tbody id="logs-table-body">
                        <?php foreach ($logs as $log): ?>
                            <?php
                                $payload = json_decode($log['raw_callback'], true);
                                $resultCode = $payload['Body']['stkCallback']['ResultCode'] ?? null;
                                $resultDesc = $payload['Body']['stkCallback']['ResultDesc'] ?? '';
                                $statusClass = ($resultCode === 0) ? 'table-success' : 'table-warning';
                            ?>
                            <tr class="<?= $statusClass ?>" 
                                data-id="<?= $log['id'] ?>" 
                                data-json='<?= htmlspecialchars($log['raw_callback'], ENT_QUOTES, "UTF-8") ?>'
                                data-result="<?= $resultCode ?>">
                                <td><?= $log['id'] ?></td>
                                <td><?= $log['created_at'] ?></td>
                                <td>
                                    <?php if ($resultCode === 0): ?>
                                        <span class="badge bg-success">Success</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Failed</span>
                                    <?php endif; ?>
                                    <small class="text-muted d-block"><?= esc($resultDesc) ?></small>
                                </td>
                                <td>
                                    <pre class="mb-0 bg-dark text-success p-2 rounded small" style="cursor:pointer;"><?= htmlspecialchars(substr($log['raw_callback'], 0, 200)) ?><?= strlen($log['raw_callback']) > 200 ? '…' : '' ?></pre>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<!-- 🪟 Modal for Viewing Full JSON -->
<div class="modal fade" id="jsonModal" tabindex="-1" aria-labelledby="jsonModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title" id="jsonModalLabel">📦 M-PESA Callback Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body bg-light">
                <pre id="jsonContent" class="p-3 rounded bg-white border small"></pre>
            </div>
        </div>
    </div>
</div>

<style>
    pre {
        white-space: pre-wrap;
        word-break: break-word;
    }
    .highlight {
        background-color: #c8f7c5 !important;
        transition: background-color 2s ease;
    }
</style>

<script src="<?= base_url('assets/js/jquery-3.6.0.min.js') ?>"></script>
<script>
let liveMode = true;
let lastSeenId = <?= isset($logs[0]['id']) ? $logs[0]['id'] : 0 ?>;

// 🔁 Fetch logs via AJAX
function refreshLogs() {
    if (!liveMode) return;

    const filter = $("#filter-result").val();

    $.ajax({
        url: "<?= base_url('admin/mpesa-logs') ?>",
        method: "GET",
        dataType: "json",
        success: function(data) {
            if (!data.length) return;
            let html = "";
            let newMaxId = lastSeenId;

            data.forEach(log => {
                const payload = JSON.parse(log.raw_callback);
                const resultCode = payload?.Body?.stkCallback?.ResultCode ?? null;
                const resultDesc = payload?.Body?.stkCallback?.ResultDesc ?? "";
                const isSuccess = resultCode === 0;
                const rowClass = isSuccess ? "table-success" : "table-warning";
                const resultHtml = isSuccess
                    ? '<span class="badge bg-success">Success</span>'
                    : '<span class="badge bg-warning text-dark">Failed</span>';

                // Apply filter
                if (
                    (filter === "success" && !isSuccess) ||
                    (filter === "failed" && isSuccess)
                ) return;

                html += `
                    <tr class="${rowClass}" data-id="${log.id}" data-json='${JSON.stringify(log.raw_callback).replace(/'/g, "&apos;")}' data-result="${resultCode}">
                        <td>${log.id}</td>
                        <td>${log.created_at}</td>
                        <td>${resultHtml}<small class="text-muted d-block">${resultDesc}</small></td>
                        <td>
                            <pre class="mb-0 bg-dark text-success p-2 rounded small" style="cursor:pointer;">
                                ${log.raw_callback ? log.raw_callback.substring(0, 200).replace(/</g, "&lt;") : ""}
                                ${log.raw_callback.length > 200 ? "…" : ""}
                            </pre>
                        </td>
                    </tr>
                `;
                if (log.id > newMaxId) newMaxId = log.id;
            });

            $("#logs-table-body").html(html);
            lastSeenId = newMaxId;
        }
    });
}

// 🔁 Auto-refresh every 5s
setInterval(refreshLogs, 5000);

// 🎛️ Toggle Live Mode
$("#toggle-live").on("click", function() {
    liveMode = !liveMode;
    const btn = $(this);
    if (liveMode) {
        btn.removeClass("btn-secondary").addClass("btn-success").html('<i class="bi bi-play-circle"></i> Live Mode: ON');
        refreshLogs();
    } else {
        btn.removeClass("btn-success").addClass("btn-secondary").html('<i class="bi bi-pause-circle"></i> Live Mode: OFF');
    }
});

// 🪟 Show JSON Modal
$(document).on("click", "#logs-table pre", function() {
    const jsonStr = $(this).closest("tr").attr("data-json");
    try {
        const parsed = JSON.parse(jsonStr);
        $("#jsonContent").text(JSON.stringify(parsed, null, 4));
    } catch (e) {
        $("#jsonContent").text(jsonStr);
    }
    const modal = new bootstrap.Modal(document.getElementById("jsonModal"));
    modal.show();
});

// 🧮 Refresh immediately when filter changes
$("#filter-result").on("change", refreshLogs);
</script>
<?= $this->endSection() ?>
