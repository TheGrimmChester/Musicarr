const Encore = require('@symfony/webpack-encore');

// Manually configure the runtime for if you don't use symfony/webpack-encore-bundle
// see https://symfony.com/doc/current/frontend.html#adding-webpack-encore-to-a-symfony-application
if (!Encore.isRuntimeEnvironmentConfigured()) {
    Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

Encore
    // directory where compiled assets will be stored
    .setOutputPath('public/build/')
    // public path used by the web server to access the output path
    .setPublicPath('/build')

    // only needed for CDN's or subdirectory deploy
    //.setManifestKeyPrefix('build/')

    /*
     * ENTRY CONFIG
     *
     * Each entry will result in one JavaScript file (e.g. app.js)
     * and one CSS file (e.g. app.css) if your JavaScript imports CSS.
     */
    .addEntry('app', './assets/app.js')

    // Stimulus controllers are handled in app.js

    // When enabled, Webpack "splits" your files into smaller pieces for greater optimization.
    .splitEntryChunks()

    // enables the Symfony UX Stimulus bridge (used in assets/bootstrap.js)
    .enableStimulusBridge('./assets/controllers.json')

    // will require an extra script tag for runtime.js
    // but, you probably want this, unless you're building a single-page app
    .enableSingleRuntimeChunk()

    /*
     * FEATURE CONFIG
     *
     * Enable & configure other features below. For a full
     * list of features, see:
     * https://symfony.com/doc/current/frontend.html#adding-more-features
     */
    .cleanupOutputBeforeBuild()
    .enableBuildNotifications()
    .enableSourceMaps(!Encore.isProduction())
    // enables hashed filenames (e.g. app.abc123.css)
    .enableVersioning(Encore.isProduction())

    // Babel configuration is handled by babel.config.js for Jest compatibility

    // enables Sass/SCSS support
    //.enableSassLoader()

    // uncomment if you use TypeScript
    //.enableTypeScriptLoader()

    // uncomment if you use React
    //.enableReactPreset()

    // Enable JSON loader for plugin manifest
    .addLoader({
        test: /\.json$/,
        type: 'json'
    })

    // uncomment to get integrity="..." attributes on your script & link tags
    // requires WebpackEncoreBundle 1.4 or higher
    //.enableIntegrityHashes(Encore.isProduction())

    // uncomment if you're having problems with a jQuery plugin
    .autoProvidejQuery()
;

const config = Encore.getWebpackConfig();

// Add alias for Stimulus controllers.json
config.resolve.alias = {
    ...config.resolve.alias,
    '@symfony/stimulus-bridge/controllers.json': require.resolve('./assets/controllers.json')
};

// Add resolve fallbacks and aliases for plugin dependencies
config.resolve.fallback = {
    ...config.resolve.fallback,
    'path': require.resolve('path-browserify'),
    'fs': false,
    'crypto': false
};

// Ensure @hotwired/stimulus can be resolved by plugins
config.resolve.alias['@hotwired/stimulus'] = require.resolve('@hotwired/stimulus');

// Add plugins directory to module resolution paths
config.resolve.modules = [
    ...config.resolve.modules || [],
    'node_modules',
    'plugins'
];

module.exports = config;
