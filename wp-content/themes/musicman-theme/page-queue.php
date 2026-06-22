<?php
/**
 * Template Name: Download Queue
 */
get_header(); ?>

<!-- Main Content Area -->
<div id="queue-tab" class="tab-pane active-pane">
    <div class="queue-stats">
        <span><i class="fas fa-database"></i> <span id="queueStats">Loading...</span></span>
        <div class="stat-group">
            <span class="stat-item"><i class="fas fa-clock"></i> Pending: <strong id="statPending">0</strong></span>
            <span class="stat-item"><i class="fas fa-spinner fa-pulse"></i> Downloading: <strong id="statDownloading">0</strong></span>
            <span class="stat-item"><i class="fas fa-pause"></i> Paused: <strong id="statPaused">0</strong></span>
            <span class="stat-item"><i class="fas fa-check-circle"></i> Completed: <strong id="statCompleted">0</strong></span>
            <span class="stat-item"><i class="fas fa-exclamation-triangle"></i> Failed: <strong id="statFailed">0</strong></span>
        </div>
    </div>
    <div class="filter-bar">
        <label><i class="fas fa-filter"></i> Status:</label>
        <select id="queueStatusFilter">
            <option value="all" selected>All</option>
            <option value="pending">Pending</option>
            <option value="downloading">Downloading</option>
            <option value="paused">Paused</option>
            <option value="completed">Completed</option>
            <option value="failed">Failed</option>
            <option value="stopped">Stopped</option>
        </select>
        <label><i class="fas fa-search"></i> Search:</label>
        <input type="text" id="queueSearchFilter" placeholder="Track or artist..." style="width:140px;" />
        <label><i class="fas fa-sort"></i> Sort:</label>
        <select id="queueSortBy">
            <option value="id">ID</option>
            <option value="status">Status</option>
            <option value="track">Track</option>
            <option value="added" selected>Added</option>
        </select>
        <span id="queueFilterCount" class="filter-count">Showing: 0</span>
        <button id="clearQueueFilters" class="btn-sm"><i class="fas fa-undo"></i> Reset</button>
    </div>
    <div class="toolbar">
        <div>
            <button id="refreshQueueBtn"><i class="fas fa-sync-alt"></i> Refresh</button>
            <button id="clearFailedBtn" class="btn-danger"><i class="fas fa-trash-alt"></i> Clear Failed</button>
            <button id="retryFailedBtn" class="btn-warning"><i class="fas fa-redo"></i> Retry Failed</button>
            <button id="queueSelectAllBtn" class="btn-primary"><i class="fas fa-check-double"></i> Select All</button>
            <button id="queueSelectNoneBtn" class="btn"><i class="fas fa-times"></i> Deselect</button>
        </div>
        <div id="queueBulkBar" style="display:none; background:#fff3cd; padding:2px 8px; border:1px solid #ffeeba; align-items:center; gap:4px; border-radius:2px;">
            <span><i class="fas fa-layer-group"></i> <span id="queueSelectedCount">0</span> selected</span>
            <button id="queueBulkStartBtn" class="btn-sm btn-success" title="Start"><i class="fas fa-play"></i></button>
            <button id="queueBulkPauseBtn" class="btn-sm btn-warning" title="Pause"><i class="fas fa-pause"></i></button>
            <button id="queueBulkStopBtn" class="btn-sm btn-danger" title="Stop"><i class="fas fa-stop"></i></button>
            <button id="queueBulkDeleteBtn" class="btn-sm btn-danger" title="Delete"><i class="fas fa-trash"></i></button>
        </div>
    </div>
    <div class="table-wrapper" id="queueTableWrapper">
        <table class="data-table" id="queueTable">
            <thead>
                <tr>
                    <th class="checkbox-col"><input type="checkbox" id="queueSelectAll" /></th>
                    <th>ID</th>
                    <th>Track</th>
                    <th>Quality</th>
                    <th>Status</th>
                    <th>Actions</th>
                    <th>Info</th>
                </tr>
            </thead>
            <tbody id="queueBody">
                <tr><td colspan="7" class="empty-msg"><i class="fas fa-spinner fa-pulse"></i> Loading...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<style>
    .queue-stats { background: #f8f9fa; padding: 15px; border-bottom: 1px solid #dee2e6; display: flex; justify-content: space-between; align-items: center; }
    .stat-group { display: flex; gap: 15px; }
    .stat-item { font-size: 12px; display: flex; align-items: center; gap: 5px; }
    .filter-bar { background: #fff; padding: 10px 15px; border-bottom: 1px solid #dee2e6; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
    .toolbar { padding: 10px 15px; background: #f1f3f5; border-bottom: 1px solid #dee2e6; display: flex; justify-content: space-between; align-items: center; }
    .table-wrapper { padding: 15px; }
    .data-table { width: 100%; border-collapse: collapse; background: #fff; }
    .data-table th { text-align: left; padding: 10px; border-bottom: 2px solid #dee2e6; color: #495057; }
    .data-table td { padding: 10px; border-bottom: 1px solid #eee; vertical-align: middle; }
    .checkbox-col { width: 30px; text-align: center; }
    .status-badge { padding: 3px 8px; border-radius: 12px; font-size: 10px; font-weight: bold; text-transform: uppercase; }
</style>

<?php get_footer(); ?>
