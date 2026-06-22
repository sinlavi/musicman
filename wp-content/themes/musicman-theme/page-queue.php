<?php
/**
 * Template Name: Download Queue
 */
get_header(); ?>

<div id="queue-tab" class="tab-pane active-pane">
    <div class="queue-stats">
        <span><i class="fas fa-database"></i> <span id="queueStats">Loading...</span></span>
        <div class="stat-group">
            <span class="stat-item"><i class="fas fa-clock"></i> Pending: <strong id="statPending">0</strong></span>
            <span class="stat-item"><i class="fas fa-spinner fa-pulse"></i> Downloading: <strong id="statDownloading">0</strong></span>
            <span class="stat-item"><i class="fas fa-check-circle"></i> Completed: <strong id="statCompleted">0</strong></span>
        </div>
    </div>
    <div class="filter-bar">
        <label><i class="fas fa-filter"></i> Status:</label>
        <select id="queueStatusFilter">
            <option value="all" selected>All</option>
            <option value="pending">Pending</option>
            <option value="downloading">Downloading</option>
            <option value="completed">Completed</option>
            <option value="failed">Failed</option>
        </select>
        <button id="refreshQueueBtn" class="btn-sm"><i class="fas fa-sync-alt"></i> Refresh</button>
    </div>
    <div class="table-wrapper">
        <table class="data-table" id="queueTable">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Track</th>
                    <th>Quality</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="queueBody">
                <tr><td colspan="5" class="empty-msg"><i class="fas fa-spinner fa-pulse"></i> Loading...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<?php get_footer(); ?>
