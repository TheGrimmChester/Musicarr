# Remote Plugin Discovery System

This document explains how the remote plugin discovery system works and how to set it up.

## Overview

The remote plugin discovery system allows your Musicarr application to discover and display plugins from a remote GitHub repository without requiring manual installation or configuration. This provides users with a centralized way to browse and install available plugins.

## How It Works

1. **Remote Repository**: The system fetches plugin information from a GitHub repository (currently configured for `TheGrimmChester/Musicarr-Plugins`)
2. **Plugin Registry**: The repository contains a `plugins.json` file that lists all available plugins with their metadata
3. **Local Integration**: Remote plugins are displayed alongside local plugins in the admin interface
4. **Installation**: Users can install remote plugins directly from the interface

## Repository Structure

The remote repository should contain a `plugins.json` file at the root level with the following structure:

```json
[
  {
    "name": "plugin-name",
    "version": "1.0.0",
    "author": "Author Name",
    "description": "Plugin description",
    "repository_url": "https://github.com/author/plugin-repo",
    "homepage_url": "https://github.com/author/plugin-repo",
    "license": "MIT",
    "tags": ["tag1", "tag2"],
    "last_updated": "2024-01-15T10:00:00Z"
  }
]
```

### Required Fields

- `name`: Unique plugin identifier
- `version`: Plugin version
- `author`: Plugin author name
- `description`: Plugin description
- `repository_url`: URL to the plugin's source repository

### Optional Fields

- `homepage_url`: Plugin homepage URL
- `license`: Plugin license
- `tags`: Array of tags for categorization
- `last_updated`: Last update timestamp

## Local Plugin Structure

Local plugins in your application now use a simplified `plugin.json` structure:

```json
{
    "name": "plugin-name",
    "version": "1.0.0",
    "description": "Plugin description",
    "author": "Author Name",
    "bundle_class": "Namespace\\PluginBundle"
}
```

### Required Fields for Local Plugins

- `name`: Unique plugin identifier
- `version`: Plugin version
- `description`: Plugin description
- `author`: Plugin author name
- `bundle_class`: Full class name of the plugin bundle

### Why Simplified?

The simplified structure removes redundant information that was previously stored in plugin.json files:
- **Routes**: Now handled automatically by Symfony routing
- **Menu items**: Now handled by the plugin's menu builders
- **Task operations**: Now handled by the core task system
- **Dependencies**: Now handled by Composer
- **Features**: No longer needed for core functionality

This approach follows the principle of separation of concerns and reduces maintenance overhead.

## Setup Instructions

### 1. Create the Remote Repository

1. Create a new GitHub repository (e.g., `Musicarr-Plugins`)
2. Add a `plugins.json` file with your plugin registry
3. Make the repository public

### 2. Configure the Application

The system is already configured to use `TheGrimmChester/Musicarr-Plugins` as the default repository. To change this, modify the constants in `src/Service/RemotePluginDiscoveryService.php`:

```php
private const PLUGIN_REPO_OWNER = 'YourGitHubUsername';
private const PLUGIN_REPO_NAME = 'YourRepositoryName';
```

### 3. Ensure Dependencies

Make sure your application has the following services available:
- `http_client` (Symfony HttpClient)
- `cache.app` (Cache service)

## Features

### Plugin Discovery
- Automatically fetches plugin information from the remote repository
- Caches results for 1 hour to improve performance
- Gracefully handles network failures

### User Interface
- Displays both local and remote plugins
- Visual indicators distinguish between local and remote plugins
- Search and filter functionality
- Plugin categories based on tags
- Repository links for remote plugins

### Installation
- Remote plugins can be installed directly from the interface
- Installation creates background tasks for proper plugin management
- Supports plugin upgrades and management

## Caching

The system uses Symfony's cache service to store remote plugin information for 1 hour. This reduces API calls to GitHub and improves performance.

To manually refresh the cache:
- Use the "Refresh Remote Plugins" button in the admin interface
- Or call `RemotePluginDiscoveryService::refreshRemotePlugins()`

## Error Handling

The system is designed to be resilient:
- If remote discovery fails, local plugins continue to work
- Network errors are logged but don't break the application
- Invalid plugin data is filtered out automatically

## Security Considerations

- Only public repositories are supported
- Plugin installation is handled through the existing task system
- No automatic code execution from remote sources
- All plugin operations require proper authentication

## Extending the System

### Adding New Plugin Sources

To support multiple plugin sources, you can:

1. Create additional discovery services
2. Modify `RemotePluginDiscoveryService` to support multiple repositories
3. Implement a plugin source registry

### Custom Plugin Metadata

You can extend the plugin metadata by:

1. Adding new fields to the `plugins.json` format
2. Updating the normalization methods in `RemotePluginDiscoveryService`
3. Extending the template to display new information

## Troubleshooting

### Common Issues

1. **No remote plugins displayed**
   - Check if the GitHub repository exists and is public
   - Verify the `plugins.json` file is at the repository root
   - Check application logs for API errors

2. **Cache issues**
   - Clear the application cache: `php bin/console cache:clear`
   - Use the refresh button in the admin interface

3. **Network errors**
   - Ensure your server can reach GitHub's API
   - Check firewall and proxy settings
   - Verify GitHub API rate limits

### Debugging

Enable debug logging in your Symfony configuration to see detailed information about the remote discovery process.

## Future Enhancements

Potential improvements to consider:

1. **Multiple Sources**: Support for multiple plugin repositories
2. **Plugin Ratings**: User ratings and reviews for plugins
3. **Dependency Management**: Automatic dependency resolution
4. **Update Notifications**: Notify users of available plugin updates
5. **Plugin Marketplace**: Web-based plugin browsing interface
