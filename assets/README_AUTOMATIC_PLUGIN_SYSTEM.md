# 🚀 **Truly Automatic Plugin Asset Loading System**

This system automatically discovers and loads JavaScript controllers and CSS assets from plugins **without requiring any manual registration** in the main project.

## ✨ **How It Works**

### 1. **Build-Time Discovery**
- A Node.js script (`scripts/generate-plugin-manifest.js`) automatically scans the `plugins/` directory
- Discovers all plugin directories and their assets
- Generates a `plugin-manifest.json` file with complete plugin information

### 2. **Runtime Loading**
- The plugin loader reads the generated manifest
- Automatically imports and registers all discovered controllers with Stimulus
- Loads all CSS files from plugins
- **No hardcoded plugin names or file lists required!**

### 3. **Fully Autonomous**
- Add a new plugin? Just create the directory and assets - it's automatically discovered!
- Remove a plugin? Just delete the directory - it's automatically removed!
- Rename a plugin? Just rename the directory - it's automatically updated!

## 🏗️ **Plugin Structure Convention**

```
plugins/
├── my-plugin/
│   ├── assets/
│   │   ├── my_controller.js          # ✅ Automatically discovered
│   │   ├── another_controller.js     # ✅ Automatically discovered
│   │   ├── my-styles.css            # ✅ Automatically loaded
│   │   └── controllers/
│   │       └── nested_controller.js  # ✅ Automatically discovered
│   └── templates/
└── another-plugin/
    └── assets/
        └── feature_controller.js      # ✅ Automatically discovered
```

## 🔧 **Controller Naming Convention**

- **File Pattern**: `*_controller.js` (must end with `_controller.js`)
- **Controller Name**: Automatically generated as `{plugin-name}-{file-name}`
- **Example**: `file_naming_controller.js` → `file-naming-file-naming`

## 📦 **Build Process**

The manifest is automatically generated before each build:

```bash
npm run dev      # Runs predev → generates manifest → builds
npm run build    # Runs prebuild → generates manifest → builds
```

## 🎯 **Benefits**

1. **Zero Manual Configuration**: No need to edit main project files when adding/removing plugins
2. **True Autonomy**: Plugins are completely self-contained
3. **Automatic Discovery**: New plugins are automatically detected
4. **Convention-Based**: Follows simple naming conventions
5. **Fallback Support**: Works even if manifest generation fails

## 🔍 **What Gets Discovered**

### **Controllers**
- Files ending with `_controller.js`
- Files in `assets/controllers/` subdirectories
- Recursive scanning of all plugin asset directories

### **CSS Files**
- All `.css` files in plugin asset directories
- Automatically loaded and injected into the document

## 🚀 **Adding a New Plugin**

1. **Create Plugin Directory**:
   ```bash
   mkdir -p plugins/my-new-plugin/assets
   ```

2. **Add Controller**:
   ```javascript
   // plugins/my-new-plugin/assets/my_feature_controller.js
   import { Controller } from '@hotwired/stimulus';
   
   export default class extends Controller {
       // Your controller logic here
   }
   ```

3. **Add CSS** (optional):
   ```css
   /* plugins/my-new-plugin/assets/my-styles.css */
   .my-feature { /* styles */ }
   ```

4. **Build**: Run `npm run dev` - your plugin is automatically discovered and loaded!

## 🔧 **Troubleshooting**

### **Plugin Not Loading?**
- Check console for errors
- Verify file naming convention (`*_controller.js`)
- Ensure plugin directory is in `plugins/` folder
- Check that `assets/` subdirectory exists

### **Build Errors?**
- Run `node scripts/generate-plugin-manifest.js` manually
- Check plugin directory structure
- Verify file permissions

## 📋 **Current Plugins**

The system automatically discovered these plugins:

- **File Naming Plugin**: 4 controllers, 1 CSS file
- **Downloader Plugin**: 4 controllers, 1 CSS file  
- **Supervisord Plugin**: 2 controllers, 0 CSS files

## 🎉 **Result**

Your plugin system is now **truly autonomous**! No more manual imports, no more hardcoded lists, no more manual registration. Just create plugins and they work automatically! 🚀
