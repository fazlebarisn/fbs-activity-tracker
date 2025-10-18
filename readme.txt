=== FBS Activity Tracker ===
Contributors: fazlebari
Tags: activity log, audit log, user tracking, security, monitoring, dashboard, analytics
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A modern, granular user activity and audit log WordPress plugin with a custom-designed dashboard interface for comprehensive site monitoring.

== Description ==

FBS Activity Tracker is a powerful, modern WordPress plugin that provides comprehensive user activity monitoring and audit logging capabilities. Built with a completely custom-designed dashboard interface, it offers real-time insights into user actions and system changes without relying on default WordPress admin styles.

= Key Features =

**🔍 Comprehensive Activity Tracking**
* User login/logout (successful and failed attempts)
* Post and page creation, editing, deletion, and trashing
* Plugin activation and deactivation
* Theme switching
* User profile updates and registrations
* WordPress settings changes

**📊 Modern Dashboard Interface**
* Completely custom-designed interface (no WordPress admin styles)
* Real-time activity feed with live updates
* Card-based statistics overview
* Advanced filtering and search capabilities
* Responsive design for all devices

**⚡ Advanced Filtering System**
* Filter by user, action type, object type
* Date range filtering (preset and custom ranges)
* Full-text search across log details
* Bulk actions for selected logs

**🛡️ Security & Performance**
* Custom database table for optimal performance
* Automatic cleanup of old logs (configurable retention)
* SQL injection protection with prepared statements
* Nonce verification for all AJAX requests
* User capability checks for admin access

**📈 Data Management**
* Export logs in JSON format
* Bulk delete functionality
* Configurable log retention period
* Automatic daily cleanup
* Infinite scroll for large datasets

**🎨 Modern Design System**
* CSS Grid and Flexbox layouts
* CSS custom properties for easy theming
* Smooth animations and transitions
* Dark mode support
* Accessibility features (ARIA, keyboard navigation)

= Free Version Includes =

* Core activity tracking for essential user actions
* Modern dashboard with real-time activity feed
* Advanced filtering and search capabilities
* Basic statistics (today's activity, active users, total logs)
* Bulk actions (delete, export selected logs)
* Automatic log cleanup (30-day retention)
* Responsive design for all devices
* Export functionality (JSON format)

= Perfect For =

* **Website Administrators** - Monitor user activities and system changes
* **Security Professionals** - Track login attempts and suspicious activities
* **Content Managers** - Keep track of content changes and updates
* **Developers** - Debug issues and monitor plugin/theme changes
* **Business Owners** - Ensure compliance and audit requirements

= Technical Specifications =

* **Database**: Custom table `wp_fbs_activity_logs` for optimal performance
* **Security**: All data sanitized, validated, and escaped
* **Performance**: Indexed database queries, AJAX loading, infinite scroll
* **Compatibility**: WordPress 5.0+, PHP 7.4+, MySQL 5.6+
* **Standards**: Follows WordPress coding standards and best practices

= Installation =

1. Upload the plugin files to `/wp-content/plugins/fbs-activity-tracker/` directory
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Navigate to 'Activity Tracker' in the admin menu to access the dashboard
4. Start monitoring user activities immediately

= Frequently Asked Questions =

= Does this plugin affect site performance? =

No, FBS Activity Tracker is designed for optimal performance. It uses a custom database table with proper indexing, AJAX-based loading, and automatic cleanup of old logs to ensure minimal impact on your site's performance.

= How long are logs kept? =

By default, logs are kept for 30 days and automatically cleaned up daily. This retention period is configurable in the plugin settings.

= Can I export the activity logs? =

Yes, you can export logs in JSON format. The plugin supports both bulk export of selected logs and full export with applied filters.

= Is the plugin secure? =

Absolutely. The plugin follows WordPress security best practices including data sanitization, validation, nonce verification, capability checks, and SQL injection protection with prepared statements.

= Does it work with multisite? =

Yes, FBS Activity Tracker is fully compatible with WordPress multisite installations and will track activities across all sites in the network.

= Can I customize the dashboard appearance? =

Yes, the plugin uses CSS custom properties (variables) for easy theming. You can customize colors, spacing, and other design elements through CSS.

== Screenshots ==

1. Modern dashboard interface with activity feed and statistics
2. Advanced filtering system with multiple filter options
3. Real-time activity feed with user avatars and action details
4. Bulk actions interface for managing multiple logs
5. Responsive design on mobile devices
6. Export functionality with JSON format

== Changelog ==

= 1.0.0 =
* Initial release
* Core activity tracking functionality
* Modern dashboard interface with custom design
* Advanced filtering and search capabilities
* Bulk actions and export functionality
* Automatic log cleanup system
* Responsive design for all devices
* Security best practices implementation
* AJAX-based real-time updates
* Infinite scroll for large datasets
* Dark mode support
* Accessibility features

== Upgrade Notice ==

= 1.0.0 =
Initial release of FBS Activity Tracker. Install to start monitoring user activities with a modern, custom-designed dashboard interface.

== Support ==

For support, feature requests, or bug reports, please visit our support page or contact us directly.

== Privacy Policy ==

FBS Activity Tracker collects and stores user activity data locally on your WordPress installation. No data is sent to external servers. All data is stored in your database and can be exported or deleted at any time. The plugin respects user privacy and only tracks activities that are necessary for security and audit purposes.

== Credits ==

Developed by Fazle Bari with modern web technologies and WordPress best practices.

== Donate ==

If you find this plugin useful, please consider making a donation to support continued development and improvements.
