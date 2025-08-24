# Automatic Plugin Asset Loading System

This system automatically discovers and loads JavaScript controllers and CSS assets from plugins without requiring manual imports in the main application.

## How It Works

### 1. Dynamic Discovery
The `plugin_loader.js` module uses webpack's `require.context()` to scan the `plugins/` directory for:
- JavaScript controller files (`*controller*.js`)
- CSS asset files (`.css`, `.scss`, `.sass`)

### 2. Automatic Registration
- **Controllers**: Automatically registered with Stimulus using a naming convention that prevents conflicts
- **CSS**: Automatically loaded into the document head with duplicate prevention

### 3. Hot Loading
New plugins can be installed and their assets will be automatically discovered on the next webpack build.

## Plugin Structure

For automatic discovery to work, plugins should follow this structure:

```
plugins/
  ├── my-plugin/
  │   ├── assets/
  │   │   ├── my_plugin_controller.js      # Auto-discovered JS controller
  │   │   ├── plugin-styles.css            # Auto-discovered CSS
  │   │   ├── custom.scss                  # Auto-discovered SCSS
  │   │   ├── other_controller.js          # Auto-discovered JS controller
  │   │   └── helpers.js                   # Ignored (not a controller)
  │   ├── src/
  │   └── plugin.json
```

## Asset Discovery Rules

### JavaScript Controllers
The system will automatically discover files that:
- ✅ Are located in `plugins/*/assets/` directories
- ✅ Have filenames containing "controller"
- ✅ Have `.js` extension
- ❌ Are named `controllers.js` (excluded to avoid conflicts)
- ❌ Are in `node_modules/`, `tests/`, or `test/` directories

### CSS Assets
The system will automatically discover files that:
- ✅ Are located in `plugins/*/assets/` directories
- ✅ Have `.css`, `.scss`, or `.sass` extensions
- ❌ Are in `node_modules/`, `tests/`, or `test/` directories

## Controller Naming Convention

Controllers are automatically named using this pattern:
- File: `my_plugin_track_controller.js` → Controller name: `my-plugin-track`
- File: `download_controller.js` in `downloader-plugin/` → Controller name: `downloader-download`

This ensures plugin controllers don't conflict with core application controllers.

## CSS Loading Features

### Automatic Loading
- CSS files are automatically loaded when the plugin system initializes
- Each plugin's CSS is loaded only once (duplicate prevention)
- CSS is added to the document head for proper cascade order

### CSS File Types Supported
- `.css` - Standard CSS files
- `.scss` - Sass files (compiled by webpack)
- `.sass` - Sass files (compiled by webpack)

### CSS Loading Process
1. **Discovery**: Webpack scans for CSS files in plugin assets directories
2. **Loading**: CSS files are dynamically added as `<link>` elements
3. **Identification**: Each CSS link is tagged with `data-plugin` attribute
4. **Deduplication**: Prevents loading the same CSS file multiple times

## Plugin Controller Format

Controllers should export a default Stimulus controller class:

```javascript
import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static targets = ["button"]
    
    connect() {
        console.log("Plugin controller connected")
    }
    
    // Your controller methods here
}
```

## Plugin CSS Format

CSS files can be standard CSS, SCSS, or Sass:

```css
/* my-plugin-styles.css */
.my-plugin {
    --primary-color: #007bff;
}

.my-plugin-button {
    background: var(--primary-color);
    padding: 0.5rem 1rem;
    border-radius: 0.25rem;
}
```

## Manual Plugin Registration (Advanced)

If you need custom controller names or special registration logic, you can create an `assets/controllers.js` file in your plugin:

```javascript
import { application } from "@hotwired/stimulus"
import MyCustomController from "./my_custom_controller"

// Manual registration with custom name
application.register("custom-name", MyCustomController)
```

## Debugging

To debug the plugin loading system:

1. Check browser console for loading messages
2. Look for "✓ Registered plugin controller" success messages
3. Look for "✓ Loaded CSS for plugin" success messages
4. Check for "✗ Failed to load" error messages
5. Verify plugin structure follows the expected format
6. Check Network tab for CSS file loading

## Benefits

- **Fully Autonomous**: Plugins can be installed without modifying core application files
- **No Manual Configuration**: Controllers and CSS are discovered automatically
- **Conflict Prevention**: Automatic naming prevents controller name conflicts
- **CSS Integration**: Plugin styles are automatically loaded and available
- **Hot Reloading**: Works with webpack hot module replacement during development
- **Duplicate Prevention**: CSS files are loaded only once per plugin

## Migration from Manual Imports

If you have existing manual imports like:

```javascript
// OLD: Manual import
import DownloaderController from './downloader_controller.js';

app.register('downloader', DownloaderController);
```

These can be safely removed. The new system will automatically discover and register the controller with an appropriate name.

## Example Plugin Structure

Here's a complete example of a plugin with both JS and CSS assets:

```
plugins/my-feature-plugin/
├── assets/
│   ├── my_feature_controller.js    # Auto-discovered JS controller
│   ├── my_feature_styles.css      # Auto-discovered CSS
│   └── theme.scss                 # Auto-discovered SCSS
├── src/
│   └── MyFeaturePlugin.php
├── templates/
│   └── feature.html.twig
└── plugin.json
```

The system will automatically:
1. Register `my-feature` controller
2. Load `my_feature_styles.css`
3. Load and compile `theme.scss`
4. Make all assets available to your application
