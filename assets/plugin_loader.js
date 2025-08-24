// plugin_loader.js
// Import the Stimulus app instance from app.js
import { app } from './app.js'

console.log('🔌 Plugin loader starting, app instance:', app)

// --- 1. DISCOVER CONTROLLERS ---
// Look for any *controller*.js inside plugins/*/assets/
const controllerContext = require.context(
  "../plugins/", // relative to assets/ directory
  true,
  /.*controller.*\.js$/i  // Simplified regex to catch more files
)

console.log('🔍 Plugin controller context keys:', controllerContext.keys())
console.log('📁 Plugin controller context path:', '../plugins/')
console.log('🔍 Total files found:', controllerContext.keys().length)

controllerContext.keys().forEach((key) => {
  console.log('🔍 Processing controller key:', key)
  
  // Skip controllers.js files (these are not actual controllers)
  if (key.includes('/controllers.js')) {
    console.log('⚠️ Skipping controllers.js file:', key)
    return
  }
  
  // Check if it's a controller file
  if (!key.includes('_controller.js')) {
    console.log('⚠️ Skipping non-controller file:', key)
    return
  }
  
  // Check if it's in assets directory
  if (!key.includes('/assets/')) {
    console.log('⚠️ Skipping file not in assets directory:', key)
    return
  }
  
  const module = controllerContext(key)
  if (!module.default) {
    console.log('⚠️ Module has no default export:', key)
    return
  }

  // Extract plugin name from path
  // e.g. plugins/my-plugin/assets/my_plugin_controller.js
  const match = key.match(/\.\/([^/]+)\/assets\/(.*)_controller\.js$/i)
  if (!match) {
    console.log('⚠️ Could not parse controller path:', key)
    return
  }

  const plugin = match[1].replace(/_/g, "-")
  const file = match[2].replace(/_/g, "-").replace(/-controller$/, "")
  const controllerName = `${plugin}-${file}`

  app.register(controllerName, module.default)
  console.log(`✅ Registered plugin controller: ${controllerName}`)
})

// --- 2. DISCOVER CSS ---
// Match css, scss, sass directly in plugins/*/assets/ (not in subdirectories like vendor)
const cssContext = require.context(
  "../plugins/",
  true,
  /^[^/]+\/assets\/[^/]*\.(css|scss|sass)$/i
)

const loadedCss = new Set()

cssContext.keys().forEach((key) => {
  const plugin = key.split("/")[1]
  const cssId = `plugin-css-${plugin}-${key}`

  if (loadedCss.has(cssId)) return
  loadedCss.add(cssId)

  try {
    // Let webpack handle SCSS/SASS → CSS
    cssContext(key)
    console.log(`✓ Loaded CSS for plugin: ${plugin} (${key})`)
  } catch (err) {
    console.error(`✗ Failed to load CSS for plugin: ${plugin}`, err)
  }
})
