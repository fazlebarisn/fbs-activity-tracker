/**
 * FBS Activity Tracker - Modern Admin JavaScript
 * Custom dashboard interface with AJAX, filtering, and infinite scroll
 */

class FBSActivityTracker {
    constructor() {
        this.currentFilters = {};
        this.currentOffset = 0;
        this.isLoading = false;
        this.hasMore = true;
        this.selectedLogs = new Set();
        this.logs = [];
        
        this.init();
    }

    init() {
        this.bindEvents();
        this.loadStatistics();
        this.loadActivityLogs();
    }

    bindEvents() {
        // Filter controls
        document.getElementById('fbs-at-apply-filters')?.addEventListener('click', () => this.applyFilters());
        document.getElementById('fbs-at-clear-filters')?.addEventListener('click', () => this.clearFilters());
        document.getElementById('fbs-at-refresh-btn')?.addEventListener('click', () => this.refreshData());
        
        // Date range filter
        document.getElementById('fbs-at-date-range')?.addEventListener('change', (e) => this.handleDateRangeChange(e.target.value));
        
        // Search
        document.getElementById('fbs-at-search-btn')?.addEventListener('click', () => this.applyFilters());
        document.getElementById('fbs-at-search')?.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') this.applyFilters();
        });
        
        // Bulk actions
        document.getElementById('fbs-at-select-all')?.addEventListener('change', (e) => this.toggleSelectAll(e.target.checked));
        document.getElementById('fbs-at-bulk-delete')?.addEventListener('click', () => this.bulkDelete());
        document.getElementById('fbs-at-bulk-export')?.addEventListener('click', () => this.bulkExport());
        
        // Export
        document.getElementById('fbs-at-export-btn')?.addEventListener('click', () => this.exportLogs());
        
        // Load more
        document.getElementById('fbs-at-load-more-btn')?.addEventListener('click', () => this.loadMore());
        
        // Infinite scroll
        this.setupInfiniteScroll();
    }

    async loadStatistics() {
        try {
            const response = await this.makeAjaxRequest('fbs_at_get_statistics', {});
            
            if (response.success) {
                this.updateStatistics(response.data);
            }
        } catch (error) {
            console.error('Failed to load statistics:', error);
        }
    }

    async loadActivityLogs(reset = false) {
        if (this.isLoading) return;
        
        this.isLoading = true;
        this.showLoading();
        
        if (reset) {
            this.currentOffset = 0;
            this.hasMore = true;
            this.logs = [];
            this.clearActivityFeed();
        }

        try {
            const response = await this.makeAjaxRequest('fbs_at_get_activity_logs', {
                ...this.currentFilters,
                limit: 50,
                offset: this.currentOffset
            });

            if (response.success) {
                this.logs = reset ? response.data.logs : [...this.logs, ...response.data.logs];
                this.hasMore = response.data.has_more;
                this.renderActivityLogs(response.data.logs, reset);
                this.updateLoadMoreButton();
            } else {
                this.showError(response.data || fbsActivityTracker.strings.error);
            }
        } catch (error) {
            console.error('Failed to load activity logs:', error);
            this.showError(fbsActivityTracker.strings.error);
        } finally {
            this.isLoading = false;
            this.hideLoading();
        }
    }

    async loadMore() {
        if (!this.hasMore || this.isLoading) return;
        
        this.currentOffset += 50;
        await this.loadActivityLogs(false);
    }

    applyFilters() {
        this.currentFilters = {
            user_id: document.getElementById('fbs-at-user-filter')?.value || '',
            action_type: document.getElementById('fbs-at-action-filter')?.value || '',
            object_type: document.getElementById('fbs-at-object-filter')?.value || '',
            search: document.getElementById('fbs-at-search')?.value || '',
            date_from: document.getElementById('fbs-at-date-from')?.value || '',
            date_to: document.getElementById('fbs-at-date-to')?.value || ''
        };

        // Remove empty filters
        Object.keys(this.currentFilters).forEach(key => {
            if (!this.currentFilters[key]) {
                delete this.currentFilters[key];
            }
        });

        this.loadActivityLogs(true);
    }

    clearFilters() {
        // Reset all filter inputs
        document.getElementById('fbs-at-user-filter').value = '';
        document.getElementById('fbs-at-action-filter').value = '';
        document.getElementById('fbs-at-object-filter').value = '';
        document.getElementById('fbs-at-search').value = '';
        document.getElementById('fbs-at-date-range').value = '';
        document.getElementById('fbs-at-date-from').value = '';
        document.getElementById('fbs-at-date-to').value = '';
        
        // Hide custom date inputs
        document.getElementById('fbs-at-custom-date-row').style.display = 'none';
        
        // Clear current filters and reload
        this.currentFilters = {};
        this.loadActivityLogs(true);
    }

    handleDateRangeChange(value) {
        const customDateRow = document.getElementById('fbs-at-custom-date-row');
        const dateFrom = document.getElementById('fbs-at-date-from');
        const dateTo = document.getElementById('fbs-at-date-to');
        
        if (value === 'custom') {
            customDateRow.style.display = 'grid';
            return;
        }
        
        customDateRow.style.display = 'none';
        
        // Set date range based on selection
        const now = new Date();
        let fromDate = '';
        let toDate = '';
        
        switch (value) {
            case 'today':
                fromDate = toDate = this.formatDate(now);
                break;
            case 'yesterday':
                const yesterday = new Date(now);
                yesterday.setDate(yesterday.getDate() - 1);
                fromDate = toDate = this.formatDate(yesterday);
                break;
            case 'last7days':
                const weekAgo = new Date(now);
                weekAgo.setDate(weekAgo.getDate() - 7);
                fromDate = this.formatDate(weekAgo);
                toDate = this.formatDate(now);
                break;
            case 'last30days':
                const monthAgo = new Date(now);
                monthAgo.setDate(monthAgo.getDate() - 30);
                fromDate = this.formatDate(monthAgo);
                toDate = this.formatDate(now);
                break;
            case 'thismonth':
                fromDate = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().split('T')[0];
                toDate = this.formatDate(now);
                break;
            case 'lastmonth':
                const lastMonth = new Date(now.getFullYear(), now.getMonth() - 1, 1);
                const lastMonthEnd = new Date(now.getFullYear(), now.getMonth(), 0);
                fromDate = this.formatDate(lastMonth);
                toDate = this.formatDate(lastMonthEnd);
                break;
        }
        
        if (fromDate) {
            this.currentFilters.date_from = fromDate;
            this.currentFilters.date_to = toDate;
        } else {
            delete this.currentFilters.date_from;
            delete this.currentFilters.date_to;
        }
        
        this.loadActivityLogs(true);
    }

    formatDate(date) {
        return date.toISOString().split('T')[0];
    }

    renderActivityLogs(logs, reset = false) {
        const feed = document.getElementById('fbs-at-activity-feed');
        
        if (reset) {
            feed.innerHTML = '';
        }
        
        if (logs.length === 0 && reset) {
            this.showNoResults();
            return;
        }
        
        this.hideNoResults();
        
        logs.forEach(log => {
            const logElement = this.createLogElement(log);
            feed.appendChild(logElement);
        });
    }

    createLogElement(log) {
        const div = document.createElement('div');
        div.className = 'fbs-at-activity-item';
        div.dataset.logId = log.id;
        
        div.innerHTML = `
            <div class="fbs-at-activity-checkbox">
                <input type="checkbox" class="fbs-at-checkbox fbs-at-log-checkbox" 
                       value="${log.id}" onchange="fbsActivityTrackerApp.toggleLogSelection(${log.id}, this.checked)">
            </div>
            <div class="fbs-at-activity-avatar">
                ${log.user_avatar}
            </div>
            <div class="fbs-at-activity-content">
                <div class="fbs-at-activity-header-item">
                    <span class="fbs-at-activity-user">${this.escapeHtml(log.user_name || 'Unknown User')}</span>
                    <span class="fbs-at-activity-action fbs-at-activity-action-${log.action_color}">
                        ${this.escapeHtml(log.action_label)}
                    </span>
                </div>
                <div class="fbs-at-activity-details">
                    ${this.escapeHtml(log.details)}
                </div>
                <div class="fbs-at-activity-meta">
                    <div class="fbs-at-activity-time">
                        <span>🕒</span>
                        <span>${this.escapeHtml(log.formatted_time)}</span>
                    </div>
                    ${log.user_ip ? `
                        <div class="fbs-at-activity-ip">
                            <span>🌐</span>
                            <span>${this.escapeHtml(log.user_ip)}</span>
                        </div>
                    ` : ''}
                </div>
            </div>
        `;
        
        return div;
    }

    toggleLogSelection(logId, checked) {
        if (checked) {
            this.selectedLogs.add(logId);
        } else {
            this.selectedLogs.delete(logId);
        }
        
        this.updateBulkActions();
        this.updateSelectAllCheckbox();
    }

    toggleSelectAll(checked) {
        const checkboxes = document.querySelectorAll('.fbs-at-log-checkbox');
        
        checkboxes.forEach(checkbox => {
            checkbox.checked = checked;
            const logId = parseInt(checkbox.value);
            
            if (checked) {
                this.selectedLogs.add(logId);
            } else {
                this.selectedLogs.delete(logId);
            }
        });
        
        this.updateBulkActions();
    }

    updateSelectAllCheckbox() {
        const selectAllCheckbox = document.getElementById('fbs-at-select-all');
        const checkboxes = document.querySelectorAll('.fbs-at-log-checkbox');
        const checkedBoxes = document.querySelectorAll('.fbs-at-log-checkbox:checked');
        
        if (checkedBoxes.length === 0) {
            selectAllCheckbox.indeterminate = false;
            selectAllCheckbox.checked = false;
        } else if (checkedBoxes.length === checkboxes.length) {
            selectAllCheckbox.indeterminate = false;
            selectAllCheckbox.checked = true;
        } else {
            selectAllCheckbox.indeterminate = true;
        }
    }

    updateBulkActions() {
        const bulkActions = document.getElementById('fbs-at-bulk-actions');
        const selectedCount = document.getElementById('fbs-at-selected-count');
        
        if (this.selectedLogs.size > 0) {
            bulkActions.style.display = 'flex';
            selectedCount.textContent = this.selectedLogs.size;
        } else {
            bulkActions.style.display = 'none';
        }
    }

    async bulkDelete() {
        if (this.selectedLogs.size === 0) {
            alert(fbsActivityTracker.strings.selectLogs);
            return;
        }
        
        if (!confirm(fbsActivityTracker.strings.confirmDelete)) {
            return;
        }
        
        try {
            const response = await this.makeAjaxRequest('fbs_at_delete_logs', {
                log_ids: Array.from(this.selectedLogs)
            });
            
            if (response.success) {
                alert(response.data.message);
                this.selectedLogs.clear();
                this.updateBulkActions();
                this.loadActivityLogs(true);
                this.loadStatistics();
            } else {
                alert(response.data || fbsActivityTracker.strings.error);
            }
        } catch (error) {
            console.error('Failed to delete logs:', error);
            alert(fbsActivityTracker.strings.error);
        }
    }

    async bulkExport() {
        if (this.selectedLogs.size === 0) {
            alert(fbsActivityTracker.strings.selectLogs);
            return;
        }
        
        // Create a form for export
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = fbsActivityTracker.ajaxUrl;
        form.target = '_blank';
        
        // Add form fields
        const fields = {
            action: 'fbs_at_export_logs',
            nonce: fbsActivityTracker.nonce,
            log_ids: Array.from(this.selectedLogs).join(',')
        };
        
        Object.keys(fields).forEach(key => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = fields[key];
            form.appendChild(input);
        });
        
        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    }

    async exportLogs() {
        // Create a form for export with current filters
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = fbsActivityTracker.ajaxUrl;
        form.target = '_blank';
        
        // Add form fields
        const fields = {
            action: 'fbs_at_export_logs',
            nonce: fbsActivityTracker.nonce,
            ...this.currentFilters
        };
        
        Object.keys(fields).forEach(key => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = fields[key];
            form.appendChild(input);
        });
        
        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    }

    async refreshData() {
        await Promise.all([
            this.loadStatistics(),
            this.loadActivityLogs(true)
        ]);
    }

    updateStatistics(stats) {
        document.getElementById('fbs-at-today-count').textContent = stats.today_count || 0;
        document.getElementById('fbs-at-active-users').textContent = stats.top_users.length || 0;
        document.getElementById('fbs-at-total-logs').textContent = stats.total_logs || 0;
    }

    showLoading() {
        document.getElementById('fbs-at-loading').style.display = 'block';
    }

    hideLoading() {
        document.getElementById('fbs-at-loading').style.display = 'none';
    }

    showNoResults() {
        document.getElementById('fbs-at-no-results').style.display = 'block';
    }

    hideNoResults() {
        document.getElementById('fbs-at-no-results').style.display = 'none';
    }

    showError(message) {
        // Simple error display - could be enhanced with a proper notification system
        alert(message);
    }

    clearActivityFeed() {
        document.getElementById('fbs-at-activity-feed').innerHTML = '';
    }

    updateLoadMoreButton() {
        const loadMore = document.getElementById('fbs-at-load-more');
        loadMore.style.display = this.hasMore ? 'block' : 'none';
    }

    setupInfiniteScroll() {
        const feed = document.getElementById('fbs-at-activity-feed');
        
        feed.addEventListener('scroll', () => {
            if (feed.scrollTop + feed.clientHeight >= feed.scrollHeight - 100) {
                this.loadMore();
            }
        });
    }

    async makeAjaxRequest(action, data) {
        const formData = new FormData();
        formData.append('action', action);
        formData.append('nonce', fbsActivityTracker.nonce);
        
        Object.keys(data).forEach(key => {
            if (data[key] !== null && data[key] !== undefined) {
                formData.append(key, data[key]);
            }
        });
        
        const response = await fetch(fbsActivityTracker.ajaxUrl, {
            method: 'POST',
            body: formData
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        return await response.json();
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Initialize the application when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.fbsActivityTrackerApp = new FBSActivityTracker();
});
