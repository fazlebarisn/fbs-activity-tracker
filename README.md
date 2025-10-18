# FBS Activity Tracker

A modern, granular user activity and audit log WordPress plugin with a custom-designed dashboard interface.

## Features

### Free Version
- **Core Tracking**: User login/logout (successful/failed), post/page creation/editing/deletion/trashing, plugin activation/deactivation, theme activation, user profile updates, WordPress settings changes
- **Modern Dashboard**: Real-time activity feed, advanced filtering (user, date range, action type), search, card-based statistics overview
- **Data Management**: Automatic cleanup of logs older than 30 days (configurable), bulk actions (delete, export selected)
- **Basic Statistics**: Today's activity count, most active users, most common action types

## Installation

1. Upload the plugin files to `/wp-content/plugins/fbs-activity-tracker/` directory
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Navigate to 'Activity Tracker' in the admin menu to access the dashboard

## Usage

### Dashboard
- Access the dashboard via **Activity Tracker** in the WordPress admin menu
- View real-time activity feed with user actions and system changes
- Use advanced filters to narrow down specific activities
- Export logs in JSON format for external analysis

### Filtering
- **User**: Filter by specific users
- **Action Type**: Filter by type of action (login, post creation, etc.)
- **Object Type**: Filter by object type (user, post, plugin, etc.)
- **Date Range**: Filter by time periods (today, last 7 days, custom range)
- **Search**: Search within log details

### Bulk Actions
- Select multiple logs using checkboxes
- Delete selected logs
- Export selected logs

## Technical Details

### Database
- Uses custom table `wp_fbs_activity_logs` for optimal performance
- Automatic cleanup of old logs (configurable retention period)
- Indexed for fast queries

### Security
- All data is sanitized and validated
- AJAX requests protected with nonces
- User capability checks for admin access
- SQL injection protection with prepared statements

### Performance
- Custom database table for scalability
- AJAX-based loading for better user experience
- Infinite scroll for large datasets
- Optimized queries with proper indexing

## Hooks and Filters

### Actions
- `fbs_at_cleanup_logs` - Triggered for automatic log cleanup

### Filters
- `fbs_at_retention_days` - Modify log retention period (default: 30 days)
- `fbs_at_auto_cleanup` - Enable/disable automatic cleanup (default: true)

## Customization

### CSS Variables
The plugin uses CSS custom properties for easy theming:

```css
:root {
    --fbs-at-primary: #4361ee;
    --fbs-at-success: #2a9d8f;
    --fbs-at-warning: #e9c46a;
    --fbs-at-danger: #e63946;
    /* ... more variables */
}
```

### JavaScript Events
The JavaScript is modular and can be extended with custom functionality.

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher

## Support

For support and feature requests, please contact the plugin author.

## Changelog

### 1.0.0
- Initial release
- Core activity tracking functionality
- Modern dashboard interface
- Advanced filtering and search
- Bulk actions and export functionality
- Automatic log cleanup
- Responsive design
- Security best practices implementation

## License

GPL v2 or later
