# TPAK DQ System Plugin

WordPress plugin for managing data quality in survey systems with LimeSurvey integration.

## Directory Structure

```
tpak-dq-system/
├── tpak-dq-system.php          # Main plugin file
├── uninstall.php               # Cleanup script
├── README.md                   # This file
├── includes/                   # Core classes
│   ├── class-autoloader.php    # Class autoloader
│   ├── class-post-types.php    # Custom post types
│   ├── class-roles.php         # User role management
│   ├── class-api-handler.php   # LimeSurvey API integration
│   ├── class-cron.php          # Cron job management
│   ├── class-workflow.php      # Workflow engine
│   ├── class-notifications.php # Email notifications
│   └── class-validator.php     # Data validation
├── admin/                      # Admin interface
│   ├── class-admin-menu.php    # Admin menu management
│   ├── class-meta-boxes.php    # Meta box registration
│   └── class-admin-columns.php # Custom admin columns
└── assets/                     # Static resources
    ├── css/
    │   └── admin-style.css     # Admin styles
    └── js/
        └── admin-script.js     # Admin JavaScript
```

## Installation

1. Upload the `tpak-dq-system` folder to `/wp-content/plugins/`
2. Activate the plugin through WordPress admin
3. Configure settings under TPAK DQ System menu

## Development Status

This plugin is currently under development. Core structure is complete.

## Requirements

- WordPress 5.0+
- PHP 7.4+
- MySQL 5.7+

## Version

1.0.0 - Initial development version