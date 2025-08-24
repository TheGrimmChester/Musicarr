# Task-Based Plugin Management System

## Overview

The plugin management system has been updated to use the project's task system instead of Symfony Messenger. This provides better integration with the existing architecture and allows for background processing of plugin operations.

## Key Features

- **Task-Based Operations**: All plugin operations (install, uninstall, enable, disable, upgrade) are now handled as background tasks
- **Asynchronous Processing**: Plugin operations run in the background without blocking the web interface
- **Task Monitoring**: All plugin operations can be monitored through the existing task system
- **Error Handling**: Comprehensive error handling and logging for all plugin operations
- **Status Tracking**: Real-time status updates for plugin installation and configuration

## New Task Types

The following task types have been added to the system:

- `plugin_install` - Install a plugin
- `plugin_uninstall` - Uninstall a plugin  
- `plugin_enable` - Enable a plugin
- `plugin_disable` - Disable a plugin
- `plugin_upgrade` - Upgrade a plugin

## Architecture

### Task Processors

Each plugin operation has a dedicated task processor:

- `PluginInstallTaskProcessor` - Handles plugin installation
- `PluginUninstallTaskProcessor` - Handles plugin uninstallation
- `PluginEnableTaskProcessor` - Handles plugin enabling
- `PluginDisableTaskProcessor` - Handles plugin disabling
- `PluginUpgradeTaskProcessor` - Handles plugin upgrades

### Plugin Status Management

The system uses a file-based approach for tracking plugin status:

- **`bundles_enabled.json`** - Tracks which plugins are enabled/installed
- **`PluginStatusManager`** - Service for managing plugin status in the file
- **No Database Required** - All plugin status is stored in JSON files

### Task Creation

The `TaskFactory` now includes methods for creating plugin-related tasks:

```php
// Install a plugin
$task = $taskFactory->createPluginInstallTask('plugin-name');

// Uninstall a plugin
$task = $taskFactory->createPluginUninstallTask('plugin-name');

// Enable a plugin
$task = $taskFactory->createPluginEnableTask('plugin-name');

// Disable a plugin
$task = $taskFactory->createPluginDisableTask('plugin-name');

// Upgrade a plugin
$task = $taskFactory->createPluginUpgradeTask('plugin-name', '2.0.0');
```

## API Endpoints

The plugin management API now includes the following endpoints:

- `POST /admin/plugins/{name}/install` - Create installation task
- `POST /admin/plugins/{name}/uninstall` - Create uninstallation task
- `POST /admin/plugins/{name}/enable` - Create enable task
- `POST /admin/plugins/{name}/disable` - Create disable task
- `POST /admin/plugins/{name}/upgrade` - Create upgrade task
- `GET /admin/plugins/{name}/status` - Get plugin status

## Plugin Configuration

### plugin.json Updates

Plugin configuration files now include task operation definitions:

```json
{
  "task_operations": [
    {
      "name": "install",
      "type": "plugin_install",
      "description": "Install the plugin",
      "priority": 5
    },
    {
      "name": "uninstall",
      "type": "plugin_uninstall",
      "description": "Uninstall the plugin",
      "priority": 5
    },
    {
      "name": "enable",
      "type": "plugin_enable",
      "description": "Enable the plugin",
      "priority": 5
    },
    {
      "name": "disable",
      "type": "plugin_disable",
      "description": "Disable the plugin",
      "priority": 5
    },
    {
      "name": "upgrade",
      "type": "plugin_upgrade",
      "description": "Upgrade the plugin",
      "priority": 5
    }
  ]
}
```

## Usage

### Web Interface

The plugin management interface now provides:

- **Status Display**: Shows current installation and enabled status
- **Action Buttons**: Install, uninstall, enable, disable, and upgrade buttons
- **Task Feedback**: Modal showing task creation status and task ID
- **Real-time Updates**: Automatic page refresh after task creation

### Command Line

Plugin operations can also be triggered via the task system:

```bash
# Process all pending tasks (including plugin operations)
php bin/console app:process-tasks

# Process only plugin-related tasks
php bin/console app:process-tasks --type=plugin_install,plugin_uninstall,plugin_enable,plugin_disable,plugin_upgrade
```

## Benefits

### 1. **Better Integration**
- Uses existing task infrastructure
- Consistent with other application operations
- Integrated logging and monitoring

### 2. **Scalability**
- Background processing prevents UI blocking
- Can handle multiple plugin operations simultaneously
- Queue-based processing for high-load scenarios

### 3. **Reliability**
- Task persistence ensures operations aren't lost
- Retry mechanisms for failed operations
- Comprehensive error handling and logging

### 4. **Monitoring**
- Real-time task status tracking
- Integration with existing task monitoring tools
- Detailed operation logs and history

### 5. **File-Based Architecture**
- No database dependencies for plugin management
- Simple JSON-based configuration
- Easy to version control and deploy
- Portable across different environments

## Migration from Messenger

The system has been completely migrated from Symfony Messenger to the custom task system:

- ✅ **Task Processors**: All plugin operations now use dedicated task processors
- ✅ **Task Types**: New task types added for plugin management
- ✅ **API Endpoints**: RESTful endpoints for all plugin operations
- ✅ **Web Interface**: Updated UI with task-based operations
- ✅ **Configuration**: Enhanced plugin.json files with task operation definitions

## Future Enhancements

Potential improvements for the task-based plugin system:

1. **Dependency Management**: Handle plugin dependencies during installation
2. **Rollback Support**: Automatic rollback for failed operations
3. **Batch Operations**: Install/enable multiple plugins simultaneously
4. **Plugin Marketplace**: Integration with external plugin repositories
5. **Health Checks**: Automated plugin health monitoring and reporting

## Troubleshooting

### Common Issues

1. **Task Not Processing**: Ensure the task processor is running
2. **Plugin Not Found**: Check plugin name and directory structure
3. **Permission Errors**: Verify file system permissions for plugin operations

### Debugging

- Check task logs: `php bin/console app:process-tasks --verbose`
- Monitor task status in the web interface
- Review application logs for detailed error information

## Conclusion

The task-based plugin management system provides a robust, scalable, and maintainable solution for plugin operations. By leveraging the existing task infrastructure, it ensures consistency with the application architecture while providing enhanced functionality and reliability.
