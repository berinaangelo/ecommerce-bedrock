<?php

if (is_file(__DIR__.'/vendor/autoload_packages.php')) {
    require_once __DIR__.'/vendor/autoload_packages.php';
}

// AngularJS 1.x, CDN-loaded (deliberately not an npm/pnpm dependency for now —
// see docs/PLAN.md). Pinned to the last stable 1.x release with a matching SRI
// hash, since the version won't move.
const ANGULARJS_VERSION = '1.8.3';
const ANGULARJS_SRI_HASH = 'sha384-quGekMf1ic6tIOn1GgLl0Pzra4ZkFyTcaDW3hZRjORqPQe3HnTKGl+lNPpuh7Lwv';

add_action('wp_enqueue_scripts', function () {
    wp_enqueue_script(
        'angularjs',
        "https://cdn.jsdelivr.net/npm/angular@".ANGULARJS_VERSION."/angular.min.js",
        [],
        ANGULARJS_VERSION,
        true // footer, matches how the theme's own Vite assets load
    );
});

add_filter('script_loader_tag', function ($tag, $handle) {
    if ($handle !== 'angularjs') {
        return $tag;
    }

    return str_replace(' src=', ' integrity="'.ANGULARJS_SRI_HASH.'" crossorigin="anonymous" src=', $tag);
}, 10, 2);

function tailpress(): TailPress\Framework\Theme
{
    return TailPress\Framework\Theme::instance()
        ->assets(fn($manager) => $manager
            ->withCompiler(new TailPress\Framework\Assets\ViteCompiler, fn($compiler) => $compiler
                ->registerAsset('resources/css/app.css')
                ->registerAsset('resources/js/app.js')
                ->registerAsset('resources/js/angular-app.js', ['angularjs'])
                ->editorStyleFile('resources/css/editor-style.css')
            )
            ->enqueueAssets()
        )
        ->features(fn($manager) => $manager->add(TailPress\Framework\Features\MenuOptions::class))
        ->menus(fn($manager) => $manager->add('primary', __( 'Primary Menu', 'tailpress')))
        ->themeSupport(fn($manager) => $manager->add([
            'title-tag',
            'custom-logo',
            'post-thumbnails',
            'align-wide',
            'wp-block-styles',
            'responsive-embeds',
            'html5' => [
                'search-form',
                'comment-form',
                'comment-list',
                'gallery',
                'caption',
            ]
        ]));
}

tailpress();
