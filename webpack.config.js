const Encore = require('@symfony/webpack-encore').default;

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
    .cleanupOutputBeforeBuild((options) => {
        options.keep = (asset) => !/^(?:[^/]+\.(?:js|css)|entrypoints\.json|manifest\.json)$/.test(asset);
    });

module.exports = Encore.getWebpackConfig();
