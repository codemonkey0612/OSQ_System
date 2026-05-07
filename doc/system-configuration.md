# OSQ Stress Check System - Architecture & Configuration

This document provides a simple overview of the plugin's internal structure to help developers and administrators understand its configuration and data flow.

##  Directory Structure Overview

The plugin follows a modern, object-oriented structure standard for WordPress plugins. The core logic uses the `OSQ` namespace and implements an autoloader.

```text
wp-content/plugins/OSQ System/
├── admin/                  # Admin dashboard pages and settings configurations
├── assets/                 # Frontend & backend static files (CSS, JS, Images)
├── doc/                    # Project documentation (Requirements, Architectures)
├── includes/               # Core plugin logic (Classes)
│   ├── analysis/           # Logic for result analysis and stress level reporting
│   ├── auth/               # User authentication, role management, and access control
│   ├── database/           # Custom database table installations, interactions, and queries
│   ├── import/             # Functionality for importing external data (e.g., CSV imports)
│   ├── pdf/                # PDF report generation for stress check results
│   ├── questionnaire/      # Everything handling the stress check form/questionnaire
│   ├── scoring/            # Algorithms and calculations for scoring stress checks
│   └── security/           # Security functions, sanitizations, and data integrity
├── languages/              # Translation files (.mo/.po) for i18n (Japanese/English)
├── templates/              # View templates separated from core logic
└── osq-stress-check.php    # Plugin bootstrap, hooks, and autoloader initialization
```

## ⚙️ Core Configuration Points

1. Bootstrap & Autoloading (`osq-stress-check.php`):
   - The primary entry point. It sets up essential constants (`OSQ_VERSION`, `OSQ_PLUGIN_DIR`, etc.).
   - Registers activation and deactivation hooks.
   - Sets up the `spl_autoload_register` function, enabling classes within the `OSQ` namespace to load dynamically from `includes/` and `admin/` directories.

2. Database & Activation (`includes/database/`, `includes/class-activator.php`):
   - Custom database tables or necessary transient setups are generally handled within the Activator when the plugin is enabled.

3. Admin Settings (`admin/class-settings-page.php`, `admin/class-admin-menu.php`):
   - Handles the addition of settings pages into the WordPress backend. Allows configuration of system behaviors.

4. i18n (Internationalization) (`includes/class-i18n.php`):
   - Handles text domain loading from the `/languages` directory. Provides bilingual support out-of-the-box (English & Japanese).
