const Encore = require('@symfony/webpack-encore');

const isProduction = Encore.isProduction();

Encore
    .setOutputPath('assets/')
    .setPublicPath('./')
    .setManifestKeyPrefix('./')
    .addEntry('sympress-mailer-admin-backend', './Resources/ts/admin.ts')
    .enableTypeScriptLoader((options) => {
        options.transpileOnly = true;
    })
    .enableSourceMaps(!isProduction)
    .disableSingleRuntimeChunk()
    .cleanupOutputBeforeBuild(['*.js', '*.css', 'entrypoints.json', 'manifest.json']);

module.exports = Encore.getWebpackConfig();
