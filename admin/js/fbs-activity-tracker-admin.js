/**
 * FBS Activity Tracker - Modern Admin JavaScript
 * Custom-designed dashboard interface
 * @author Fazle Bari <fazlebarisn@gmail.com>
 * @since 1.0.0
 */

(function() {
    'use strict';

    // Main application object
    const FBSActivityTracker = {
        // Configuration
        config: {
            ajaxUrl: fbsActivityTracker.ajaxUrl,
            nonce: fbsActivityTracker.nonce,
            strings: fbsActivityTracker.strings,
            actions: fbsActivityTracker.actions,
            itemsPerPage: 50,
            currentOffset: 0,
            isLoading: false,
            selectedLogs: new Set()
        },

        // Initialize the application
        init() {
            this.bindEvents();
            this.loadStatistics();
            this.loadActivityLogs();
            this.setupInfiniteScroll();
        },

        // Bind event listeners
        bindEvents() {
            // Filter controls
            document.getElementById('fbs-at-apply-filters')?.addEventListener('click', () => this.applyFilters());
            document.getElementById('fbs-at-clear-filters')?.addEventListener('click', () => this.clearFilters());
            document.getElementById('fbs-at-search-btn')?.addEventListener('click', () => this.applyFilters());
            document.getElementById('fbs-at-search')?.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') this.applyFilters();
            });

            // Date range filter
            document.getElementById('fbs-at-date-range')?.addEventListener('change', (e) => {
                this.handleDateRangeChange(e.target.value);
            });

            // Bulk actions
            document.getElementById('fbs-at-select-all')?.addEventListener('change', (e) => {
                this.toggleSelectAll(e.target.checked);
            });
            document.getElementById('fbs-at-bulk-delete')?.addEventListener('click', () => this.bulkDelete());
            document.getElementById('fbs-at-bulk-export')?.addEventListener('click', () => this.bulkExport());

            // Header actions
            document.getElementById('fbs-at-refresh-btn')?.addEventListener('click', () => this.refresh());
            document.getElementById('fbs-at-export-btn')?.addEventListener('click', () => this.exportAll());

            // Load more
            document.getElementById('fbs-at-load-more-btn')?.addEventListener('click', () => this.loadMore());

            // Event delegation for dynamically created checkboxes
            const activityFeed = document.getElementById('fbs-at-activity-feed');
            if (activityFeed) {
                activityFeed.addEventListener('change', (e) => {
                    if (e.target.classList.contains('fbs-at-log-checkbox')) {
                        this.handleLogSelection(e.target);
                    }
                });
                
                // Also handle click events as a fallback
                activityFeed.addEventListener('click', (e) => {
                    if (e.target.classList.contains('fbs-at-log-checkbox')) {
                        // Small delay to ensure the checkbox state has updated
                        setTimeout(() => {
                            this.handleLogSelection(e.target);
                        }, 10);
                    }
                });
            }
        },

        // Load dashboard statistics
        async loadStatistics() {
            try {
                const response = await this.makeAjaxRequest(this.config.actions.getStats, {});
                
                if (response.success) {
                    this.updateStatistics(response.data);
                }
            } catch (error) {
                console.error('Failed to load statistics:', error);
            }
        },

        // Update statistics display
        updateStatistics(data) {
            const todayCountEl = document.getElementById('fbs-at-today-count');
            const totalLogsEl = document.getElementById('fbs-at-total-logs');
            const activeUsersEl = document.getElementById('fbs-at-active-users');

            if (todayCountEl) todayCountEl.textContent = data.today_count || 0;
            if (totalLogsEl) totalLogsEl.textContent = data.total_logs || 0;
            if (activeUsersEl) activeUsersEl.textContent = data.top_users?.length || 0;
        },

        // Load activity logs
        async loadActivityLogs(reset = true) {
            if (this.config.isLoading) return;

            this.config.isLoading = true;
            this.showLoading();

            if (reset) {
                this.config.currentOffset = 0;
                this.clearActivityFeed();
            }

            try {
                const filters = this.getCurrentFilters();
                const response = await this.makeAjaxRequest(this.config.actions.getLogs, {
                    ...filters,
                    limit: this.config.itemsPerPage,
                    offset: this.config.currentOffset
                });

                if (response.success) {
                    this.displayActivityLogs(response.data.logs, reset);
                    this.updateLoadMoreButton(response.data.has_more);
                } else {
                    this.showError(this.config.strings.error);
                }
            } catch (error) {
                console.error('Failed to load activity logs:', error);
                this.showError(this.config.strings.error);
            } finally {
                this.config.isLoading = false;
                this.hideLoading();
            }
        },

        // Display activity logs in the feed
        displayActivityLogs(logs, reset = true) {
            const feed = document.getElementById('fbs-at-activity-feed');
            const noResults = document.getElementById('fbs-at-no-results');

            if (!feed) return;

            if (logs.length === 0 && reset) {
                this.showNoResults();
                return;
            }

            if (reset) {
                this.hideNoResults();
            }

            logs.forEach(log => {
                const logElement = this.createLogElement(log);
                feed.appendChild(logElement);
            });

            this.config.currentOffset += logs.length;
        },

        // Create individual log element
        createLogElement(log) {
            const item = document.createElement('div');
            item.className = 'fbs-at-activity-item';
            item.dataset.logId = log.id;

            item.innerHTML = `
                <div class="fbs-at-activity-checkbox">
                    <input type="checkbox" class="fbs-at-checkbox fbs-at-log-checkbox" 
                           value="${log.id}" data-log-id="${log.id}">
                </div>
                <div class="fbs-at-activity-avatar">
                    ${log.user_avatar}
                </div>
                <div class="fbs-at-activity-content">
                    <div class="fbs-at-activity-header-item">
                        <span class="fbs-at-activity-user">${this.escapeHtml(log.user_name)}</span>
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

            return item;
        },

        // Handle individual log selection
        handleLogSelection(checkbox) {
            const logId = checkbox.value;
            
            if (checkbox.checked) {
                this.config.selectedLogs.add(logId);
            } else {
                this.config.selectedLogs.delete(logId);
            }
            
            this.updateBulkActions();
            this.updateSelectAllState();
        },

        // Toggle select all logs
        toggleSelectAll(checked) {
            const checkboxes = document.querySelectorAll('.fbs-at-log-checkbox');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = checked;
                const logId = checkbox.value;
                
                if (checked) {
                    this.config.selectedLogs.add(logId);
                } else {
                    this.config.selectedLogs.delete(logId);
                }
            });

            this.updateBulkActions();
        },

        // Update select all checkbox state
        updateSelectAllState() {
            const selectAllCheckbox = document.getElementById('fbs-at-select-all');
            const checkboxes = document.querySelectorAll('.fbs-at-log-checkbox');
            
            if (!selectAllCheckbox || checkboxes.length === 0) return;

            const checkedCount = document.querySelectorAll('.fbs-at-log-checkbox:checked').length;
            
            if (checkedCount === 0) {
                selectAllCheckbox.indeterminate = false;
                selectAllCheckbox.checked = false;
            } else if (checkedCount === checkboxes.length) {
                selectAllCheckbox.indeterminate = false;
                selectAllCheckbox.checked = true;
            } else {
                selectAllCheckbox.indeterminate = true;
            }
        },

        // Update bulk actions visibility
        updateBulkActions() {
            const bulkActions = document.getElementById('fbs-at-bulk-actions');
            const selectedCount = document.getElementById('fbs-at-selected-count');
            
            if (this.config.selectedLogs.size > 0) {
                if (bulkActions) {
                    bulkActions.style.display = 'flex';
                }
                if (selectedCount) {
                    selectedCount.textContent = this.config.selectedLogs.size;
                }
            } else {
                if (bulkActions) {
                    bulkActions.style.display = 'none';
                }
            }
        },

        // Get current filter values
        getCurrentFilters() {
            const filters = {};
            
            const userFilter = document.getElementById('fbs-at-user-filter');
            if (userFilter?.value) filters.user_id = userFilter.value;

            const actionFilter = document.getElementById('fbs-at-action-filter');
            if (actionFilter?.value) filters.action_type = actionFilter.value;

            const objectFilter = document.getElementById('fbs-at-object-filter');
            if (objectFilter?.value) filters.object_type = objectFilter.value;

            const dateFrom = document.getElementById('fbs-at-date-from');
            if (dateFrom?.value) filters.date_from = dateFrom.value;

            const dateTo = document.getElementById('fbs-at-date-to');
            if (dateTo?.value) filters.date_to = dateTo.value;

            const search = document.getElementById('fbs-at-search');
            if (search?.value) filters.search = search.value;

            return filters;
        },

        // Apply filters
        applyFilters() {
            this.loadActivityLogs(true);
        },

        // Clear all filters
        clearFilters() {
            document.getElementById('fbs-at-user-filter').value = '';
            document.getElementById('fbs-at-action-filter').value = '';
            document.getElementById('fbs-at-object-filter').value = '';
            document.getElementById('fbs-at-date-range').value = '';
            document.getElementById('fbs-at-date-from').value = '';
            document.getElementById('fbs-at-date-to').value = '';
            document.getElementById('fbs-at-search').value = '';
            
            document.getElementById('fbs-at-custom-date-row').style.display = 'none';
            
            // Clear selected logs when clearing filters
            this.clearSelectedLogs();
            this.loadActivityLogs(true);
        },

        // Handle date range filter change
        handleDateRangeChange(value) {
            const customDateRow = document.getElementById('fbs-at-custom-date-row');
            const dateFrom = document.getElementById('fbs-at-date-from');
            const dateTo = document.getElementById('fbs-at-date-to');
            
            if (value === 'custom') {
                customDateRow.style.display = 'flex';
                return;
            }
            
            customDateRow.style.display = 'none';
            
            if (!value) {
                dateFrom.value = '';
                dateTo.value = '';
                // Automatically apply filters when "All Time" is selected
                this.applyFilters();
                return;
            }

            const today = new Date();
            let fromDate, toDate;

            switch (value) {
                case 'today':
                    fromDate = toDate = new Date(today);
                    break;
                case 'yesterday':
                    fromDate = toDate = new Date(today);
                    fromDate.setDate(today.getDate() - 1);
                    break;
                case 'last7days':
                    fromDate = new Date(today);
                    fromDate.setDate(today.getDate() - 7);
                    toDate = new Date(today);
                    break;
                case 'last30days':
                    fromDate = new Date(today);
                    fromDate.setDate(today.getDate() - 30);
                    toDate = new Date(today);
                    break;
                case 'thismonth':
                    fromDate = new Date(today.getFullYear(), today.getMonth(), 1);
                    toDate = new Date(today);
                    break;
                case 'lastmonth':
                    fromDate = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                    toDate = new Date(today.getFullYear(), today.getMonth(), 0);
                    break;
            }

            if (fromDate && toDate) {
                const fromDateStr = this.formatDate(fromDate);
                const toDateStr = this.formatDate(toDate);
                dateFrom.value = fromDateStr;
                dateTo.value = toDateStr;
                // Automatically apply filters when a predefined date range is selected
                this.applyFilters();
            }
        },

        // Format date for input
        formatDate(date) {
            return date.toISOString().split('T')[0];
        },

        // Load more logs
        loadMore() {
            this.loadActivityLogs(false);
        },

        // Update load more button
        updateLoadMoreButton(hasMore) {
            const loadMore = document.getElementById('fbs-at-load-more');
            if (loadMore) {
                loadMore.style.display = hasMore ? 'block' : 'none';
            }
        },

        // Bulk delete selected logs
        async bulkDelete() {
            if (this.config.selectedLogs.size === 0) {
                alert(this.config.strings.selectLogs);
                return;
            }

            if (!confirm(this.config.strings.confirmDelete)) {
                return;
            }

            try {
                const logIds = Array.from(this.config.selectedLogs);
                
                const response = await this.makeAjaxRequest(this.config.actions.deleteLogs, {
                    log_ids: logIds
                });

                if (response.success) {
                    this.showSuccess(response.data.message);
                    this.clearSelectedLogs();
                    this.loadActivityLogs(true);
                    this.loadStatistics();
                } else {
                    this.showError(response.data || this.config.strings.error);
                }
            } catch (error) {
                console.error('Failed to delete logs:', error);
                this.showError(this.config.strings.error);
            }
        },

        // Bulk export selected logs
        async bulkExport() {
            if (this.config.selectedLogs.size === 0) {
                alert(this.config.strings.selectLogs);
                return;
            }

            const filters = this.getCurrentFilters();
            filters.log_ids = Array.from(this.config.selectedLogs);
            
            this.exportLogs(filters);
        },

        // Export all logs
        exportAll() {
            const filters = this.getCurrentFilters();
            this.exportLogs(filters);
        },

        // Export logs
        exportLogs(filters) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = this.config.ajaxUrl;
            form.target = '_blank';

            // Add nonce
            const nonceInput = document.createElement('input');
            nonceInput.type = 'hidden';
            nonceInput.name = 'nonce';
            nonceInput.value = this.config.nonce;
            form.appendChild(nonceInput);

            // Add action
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = this.config.actions.exportLogs;
            form.appendChild(actionInput);

            // Add filters
            Object.keys(filters).forEach(key => {
                if (filters[key] !== undefined && filters[key] !== '') {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = key;
                    input.value = Array.isArray(filters[key]) ? JSON.stringify(filters[key]) : filters[key];
                    form.appendChild(input);
                }
            });

            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);

            this.showSuccess(this.config.strings.exportSuccess);
        },

        // Refresh data
        refresh() {
            this.loadStatistics();
            this.loadActivityLogs(true);
        },

        // Setup infinite scroll
        setupInfiniteScroll() {
            const feed = document.getElementById('fbs-at-activity-feed');
            if (!feed) return;

            feed.addEventListener('scroll', () => {
                if (this.config.isLoading) return;

                const { scrollTop, scrollHeight, clientHeight } = feed;
                const threshold = 100;

                if (scrollTop + clientHeight >= scrollHeight - threshold) {
                    this.loadMore();
                }
            });
        },

        // Make AJAX request
        async makeAjaxRequest(action, data) {
            const formData = new FormData();
            formData.append('action', action);
            formData.append('nonce', this.config.nonce);

            Object.keys(data).forEach(key => {
                if (data[key] !== undefined && data[key] !== '') {
                    if (Array.isArray(data[key])) {
                        // For arrays, send each item as a separate parameter
                        data[key].forEach((item, index) => {
                            formData.append(`${key}[${index}]`, item);
                        });
                    } else {
                        formData.append(key, data[key]);
                    }
                }
            });

            const response = await fetch(this.config.ajaxUrl, {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            return await response.json();
        },

        // Show loading state
        showLoading() {
            const loading = document.getElementById('fbs-at-loading');
            if (loading) {
                loading.style.display = 'flex';
            }
        },

        // Hide loading state
        hideLoading() {
            const loading = document.getElementById('fbs-at-loading');
            if (loading) {
                loading.style.display = 'none';
            }
        },

        // Show no results
        showNoResults() {
            const noResults = document.getElementById('fbs-at-no-results');
            if (noResults) {
                noResults.style.display = 'flex';
            }
        },

        // Hide no results
        hideNoResults() {
            const noResults = document.getElementById('fbs-at-no-results');
            if (noResults) {
                noResults.style.display = 'none';
            }
        },

        // Clear activity feed
        clearActivityFeed() {
            const feed = document.getElementById('fbs-at-activity-feed');
            if (feed) {
                feed.innerHTML = '';
            }
            // Don't clear selected logs here - they should persist across data loads
            this.updateBulkActions();
        },

        // Clear selected logs (used after successful operations)
        clearSelectedLogs() {
            this.config.selectedLogs.clear();
            this.updateBulkActions();
        },

        // Show success message
        showSuccess(message) {
            this.showNotification(message, 'success');
        },

        // Show error message
        showError(message) {
            this.showNotification(message, 'error');
        },

        // Show notification
        showNotification(message, type = 'info') {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `fbs-at-notification fbs-at-notification-${type}`;
            notification.innerHTML = `
                <div class="fbs-at-notification-content">
                    <span class="fbs-at-notification-message">${this.escapeHtml(message)}</span>
                    <button class="fbs-at-notification-close" onclick="this.parentElement.parentElement.remove()">×</button>
                </div>
            `;

            // Add to page
            document.body.appendChild(notification);

            // Auto remove after 5 seconds
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                }
            }, 5000);
        },

        // Escape HTML to prevent XSS
        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => FBSActivityTracker.init());
    } else {
        FBSActivityTracker.init();
    }

})();