# Remote Plugin Installation System

This document explains how to install plugins directly from public remote Git repositories.

## Overview

The remote plugin installation system allows you to install plugins directly from public GitHub repositories without manually downloading and extracting files. It includes automatic dependency management.

## Features

- **Direct Repository Installation**: Install plugins directly from public Git repositories
- **Branch Selection**: Choose specific branches for installation
- **Automatic Dependencies**: Composer dependencies are automatically installed
- **Validation**: Ensures proper plugin structure before installation
- **Cleanup**: Automatic cleanup on failed installations
- **Update Support**: Update existing plugins from their repositories

## Installation Methods

### 1. Web Interface

Use the admin panel at `/admin/plugins` to install plugins through a web form:

1. Navigate to **Admin > Plugins**
2. Fill in the **Install Plugin from Repository** form:
   - **Repository URL**: HTTPS or SSH URL of the repository
   - **Plugin Name**: Directory name for the plugin
   - **Branch**: Branch to checkout (default: main)
3. Click **Install Plugin**

### 2. Command Line

Use the Symfony console command for installation:

```bash
# Install from public repository
php bin/console app:remote-plugin install my-plugin --repository=https://github.com/user/repo

# Install specific branch
php bin/console app:remote-plugin install my-plugin --repository=https://github.com/user/repo --branch=develop

# Install from SSH URL
php bin/console app:remote-plugin install my-plugin --repository=git@github.com:user/repo.git
```

## Repository URL Formats

### HTTPS URLs
```
https://github.com/username/repository-name
https://github.com/username/repository-name.git
```

### SSH URLs
```
git@github.com:username/repository-name.git
```



## Plugin Structure Requirements

For a repository to be recognized as a valid plugin, it must contain:

```
repository/
├── plugin.json          # Required: Plugin metadata
├── composer.json        # Optional: Dependencies
├── src/                 # Optional: Source code
└── templates/           # Optional: Templates
```

### plugin.json Format

```json
{
    "name": "plugin-name",
    "version": "1.0.0",
    "description": "Plugin description",
    "author": "Author Name",
    "bundle_class": "Namespace\\PluginBundle"
}
```

## Installation Process

1. **Validation**: Check repository URL format and accessibility
2. **Cloning**: Git clone the repository to plugins directory
3. **Structure Validation**: Ensure required files exist
4. **Dependency Installation**: Run `composer install` if composer.json exists
5. **Cleanup**: Remove files on failure

## Update Existing Plugins

### Web Interface
- Use the **Upgrade** button for installed plugins
- Updates are handled through the task system

### Command Line
```bash
# Update to latest commit on current branch
php bin/console app:remote-plugin update my-plugin

# Update to specific branch
php bin/console app:remote-plugin update my-plugin --branch=develop
```

## Remove Plugins

### Web Interface
- Use the **Uninstall** button for installed plugins

### Command Line
```bash
# Remove plugin (with confirmation)
php bin/console app:remote-plugin remove my-plugin

# Force remove without confirmation
php bin/console app:remote-plugin remove my-plugin --force
```

## Troubleshooting

### Common Issues

1. **Repository not found**
   - Check repository URL spelling
   - Ensure repository exists and is publicly accessible

3. **Clone failed**
   - Check network connectivity
   - Verify repository size (large repos may timeout)
   - Ensure git is installed on the system

4. **Invalid plugin structure**
   - Ensure `plugin.json` exists in repository root
   - Check JSON syntax in plugin.json
   - Verify required fields are present

### Debug Information

Enable debug logging to see detailed installation information:

```yaml
# config/packages/dev/monolog.yaml
monolog:
    handlers:
        main:
            level: debug
```

### Timeout Issues

For large repositories or slow connections, increase timeouts:

```yaml
# config/services.yaml
App\Service\RemotePluginInstallerService:
    arguments:
        $filesystem: '@filesystem'
    calls:
        - method: setCloneTimeout
          arguments: [600]  # 10 minutes
        - method: setComposerTimeout
          arguments: [900]  # 15 minutes
```

## Security Considerations

- **Repository Access**: Only install from trusted public repositories
- **Code Review**: Review plugin code before installation
- **Permissions**: Ensure proper file permissions on plugins directory

## Best Practices

1. **Use Specific Branches**: Install from stable branches (main, master, stable)
2. **Regular Updates**: Keep plugins updated for security and features
3. **Backup**: Backup plugins directory before major updates
4. **Testing**: Test plugins in development environment first
5. **Documentation**: Keep track of installed plugins and their sources

## API Reference

### RemotePluginInstallerService

```php
// Install plugin
$result = $remotePluginInstaller->installPlugin(
    $repositoryUrl,
    $pluginName,
    $branch,
    $token,
    $output
);

// Check if repository is private
$isPrivate = $remotePluginInstaller->isPrivateRepository($repositoryUrl, $token);

// Update plugin
$result = $remotePluginInstaller->updatePlugin($pluginName, $branch, $output);

// Remove plugin
$result = $remotePluginInstaller->removePlugin($pluginName);
```

### Task System Integration

Remote plugin installation is integrated with the task system:

```php
// Create installation task
$task = $taskFactory->createRemotePluginInstallTask(
    $repositoryUrl,
    $pluginName,
    $branch,
    $token,
    $data,
    $priority
);
```

## Future Enhancements

- **Multiple Repository Sources**: Support for GitLab, Bitbucket, etc.
- **Plugin Marketplace**: Web-based plugin browsing and installation
- **Dependency Resolution**: Automatic dependency conflict resolution
- **Rollback Support**: Ability to rollback to previous plugin versions
- **Plugin Signing**: GPG signature verification for plugins
