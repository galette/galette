/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/*
 * Set the URL of your local instance of Galette here.
 * Then run "gulp serve".
 * This is for local development purpose only.
 * Don't commit this change in the repository.
 */
const localServer = {
  url: 'http://galette.localhost/'
}

var gulp = require('gulp'),
  del = require('del'),
  uglify = require('gulp-uglify'),
  cleanCSS = require('gulp-clean-css'),
  merge = require('merge-stream'),
  concat = require('gulp-concat'),
  replace = require('gulp-replace'),
  download = require("gulp-download2"),
  browserSync = require('browser-sync').create(),
  esbuild = require('esbuild'),
  build = require('./semantic/tasks/build'),
  buildJS = require('./semantic/tasks/build/javascript'),
  buildCSS = require('./semantic/tasks/build/css'),
  buildAssets = require('./semantic/tasks/build/assets')
;

gulp.task('build ui', build);
gulp.task('build-css', buildCSS);
gulp.task('build-javascript', buildJS);
gulp.task('build-assets', buildAssets);

var paths = {
  webroot: './galette/webroot/',
  assets: {
    public: './galette/webroot/assets/',
    css: './galette/webroot/assets/css/',
    js: './galette/webroot/assets/js/',
    webfonts: './galette/webroot/assets/webfonts/',
    theme: {
      public: './galette/webroot/themes/default/',
      css: './galette/webroot/themes/default/ui/semantic.min.css',
      js: './galette/webroot/themes/default/ui/semantic.min.js',
      images: './galette/webroot/themes/default/images/'
    }
  },
  src: {
    semantic: './semantic.json',
    theme: './ui/semantic/galette/**/*',
    config: './ui/semantic/theme*',
    files: [
      './ui/semantic/galette/*',
      './ui/semantic/galette/**/*.*'
    ],
    css: './ui/css/**/*.css',
    js: './ui/js/*.js',
    favicon:'./ui/images/favicon.png',
    logo: './ui/images/galette.webp',
    halloween_logo: './ui/images/galette_halloween.webp',
    xmas_logo: './ui/images/galette_xmas.webp',
    background:'./ui/images/bg.png',
    background_rtl:'./ui/images/bg-rtl.png',
    photo:'./ui/images/default.png',
    emojis: './node_modules/twemoji-emojis/vendor/svg/*'
  },
  semantic: {
    src: './semantic/src/',
    theme: './semantic/src/themes/galette/'
  },
  styles: {
    summernote: [
      './node_modules/summernote/dist/summernote-lite.min.css'
    ],
    codemirror: [
      'https://cdnjs.cloudflare.com/ajax/libs/codemirror/3.24.0/codemirror.css'
    ],
    maps: [
      './node_modules/leaflet/dist/leaflet.css',
      './node_modules/leaflet.fullscreen/dist/Control.FullScreen.css',
      './node_modules/leaflet-gesture-handling/dist/leaflet-gesture-handling.css',
      './node_modules/leaflet.markercluster/dist/MarkerCluster.css',
      './node_modules/leaflet.markercluster/dist/MarkerCluster.Default.css',
      './node_modules/leaflet-control-geocoder/dist/Control.Geocoder.css',
      './node_modules/leaflet-legend/leaflet-legend.css',
      './ui/css/maps.css'
    ],
    mapslocate: [
      './node_modules/leaflet.locatecontrol/dist/L.Control.Locate.min.css'
    ],
    mapsgl: [
      './node_modules/maplibre-gl/dist/maplibre-gl.css'
    ]
  },
  scripts: {
    main: [
      './node_modules/js-cookie/dist/js.cookie.js',
      './ui/js/dropdown-a11y.js',
      './ui/js/common.js'
    ],
    masschanges: [
      './ui/js/masschanges.js'
    ],
    chartjs: [
      './node_modules/chart.js/dist/chart.umd.js',
      './node_modules/chartjs-plugin-autocolors/dist/chartjs-plugin-autocolors.min.js',
      './node_modules/chartjs-plugin-datalabels/dist/chartjs-plugin-datalabels.min.js'
    ],
    sortablejs: [
      './node_modules/sortablejs/Sortable.min.js'
    ],
    summernote: [
      './node_modules/summernote/dist/summernote-lite.min.js'
    ],
    codemirror: [
      'https://cdnjs.cloudflare.com/ajax/libs/codemirror/3.24.0/codemirror.js'
    ],
    codemirrorxml: [
      'https://cdnjs.cloudflare.com/ajax/libs/codemirror/3.24.0/mode/xml/xml.js'
    ],
    codemirrorformatting: [
      'https://cdnjs.cloudflare.com/ajax/libs/codemirror/2.36.0/formatting.js'
    ],
    darkreader: [
      './node_modules/darkreader/darkreader.js'
    ],
    maps: [
      './node_modules/leaflet/dist/leaflet.js',
      './node_modules/leaflet.fullscreen/dist/Control.FullScreen.umd.js',
      './node_modules/leaflet-gesture-handling/dist/leaflet-gesture-handling.min.js',
      './node_modules/leaflet.markercluster/dist/leaflet.markercluster.js',
      './node_modules/leaflet-control-geocoder/dist/Control.Geocoder.js',
      './node_modules/leaflet-legend/leaflet-legend.js',
      './ui/js/maps.js'
    ],
    mapslocate: [
      './node_modules/leaflet.locatecontrol/dist/L.Control.Locate.min.js'
    ]
  },
  // maplibre-gl ships ES modules only since 6.x, so its bundle cannot be built
  // by concatenation like the others: esbuild rolls it up, along with the
  // Leaflet binding, into a classic script still exposing the `maplibregl` global.
  mapsgl: {
    entry: './ui/js/maps/maps-gl.js',
    // Leaflet comes from maps.min.js, so the bundle must reuse the one on the
    // page instead of embedding a second copy.
    aliases: {
      'leaflet': './ui/js/maps/leaflet-global.js'
    },
    // maplibre-gl runs its tile parsing in a worker it fetches at runtime; it
    // has to be a file of its own, next to the bundle.
    worker: './node_modules/maplibre-gl/dist/maplibre-gl-worker.mjs'
  },
  extras: [
    {
      src: './node_modules/summernote/dist/font/*',
      dest: 'webfonts/',
      encoding: false
    }, {
      src: './node_modules/summernote/dist/lang/*.min.js',
      dest: 'js/lang/'
    }, {
      src: './node_modules/jquery/dist/jquery.min.js',
      dest: 'js/'
    }, {
      // maps stylesheets refer to their images relatively
      src: './node_modules/leaflet/dist/images/*',
      dest: 'css/images/',
      encoding: false
    }, {
      src: './ui/images/maps/*',
      dest: 'images/maps/',
      encoding: false
    }
  ]
};

function galette() {
  favicon = gulp.src(paths.src.favicon, {encoding: false})
    .pipe(gulp.dest(paths.assets.theme.images))
    .pipe(browserSync.stream());

  logo = gulp.src(paths.src.logo, {encoding: false})
    .pipe(gulp.dest(paths.assets.theme.images))
    .pipe(browserSync.stream());

  halloween_logo = gulp.src(paths.src.halloween_logo, {encoding: false})
    .pipe(gulp.dest(paths.assets.theme.images))
    .pipe(browserSync.stream());

  xmas_logo = gulp.src(paths.src.xmas_logo, {encoding: false})
    .pipe(gulp.dest(paths.assets.theme.images))
    .pipe(browserSync.stream());

  photo = gulp.src(paths.src.photo, {encoding: false})
    .pipe(gulp.dest(paths.assets.theme.images))
    .pipe(browserSync.stream());

  return merge(favicon, logo, halloween_logo, xmas_logo, photo);
}

function theme() {
  config = gulp.src(paths.src.config)
    .pipe(gulp.dest(paths.semantic.src))
    .pipe(browserSync.stream());

  theme =  gulp.src(paths.src.files)
    .pipe(gulp.dest(paths.semantic.theme))
    .pipe(browserSync.stream());

  background = gulp.src(paths.src.background, {encoding: false})
    .pipe(gulp.dest(paths.semantic.theme + 'assets/images'))
    .pipe(browserSync.stream());

  background_rtl = gulp.src(paths.src.background_rtl, {encoding: false})
    .pipe(gulp.dest(paths.semantic.theme + 'assets/images'))
    .pipe(browserSync.stream());

  emojis = gulp.src(paths.src.emojis)
    .pipe(gulp.dest(paths.semantic.theme + 'assets/emojis'))
    .pipe(browserSync.stream());

  return merge(config, theme, emojis);
}

function clean() {
  return del([
    paths.assets.public,
    paths.assets.theme.public,
  ]);
}

function styles() {
  summernote = gulp.src(paths.styles.summernote)
    .pipe(replace('url(font/', 'url(../webfonts/'))
    .pipe(cleanCSS())
    .pipe(concat('summernote.min.css'))
    .pipe(gulp.dest(paths.assets.css))
    .pipe(browserSync.stream());

  codemirror = download(paths.styles.codemirror, { ci: true })
    .pipe(gulp.dest(paths.assets.css))
    .pipe(browserSync.stream());

  maps = gulp.src(paths.styles.maps)
    .pipe(cleanCSS())
    .pipe(concat('maps.min.css'))
    .pipe(gulp.dest(paths.assets.css))
    .pipe(browserSync.stream());

  mapslocate = gulp.src(paths.styles.mapslocate)
    .pipe(cleanCSS())
    .pipe(concat('maps-locate.min.css'))
    .pipe(gulp.dest(paths.assets.css))
    .pipe(browserSync.stream());

  mapsgl = gulp.src(paths.styles.mapsgl)
    .pipe(cleanCSS())
    .pipe(concat('maps-gl.min.css'))
    .pipe(gulp.dest(paths.assets.css))
    .pipe(browserSync.stream());

  return merge(summernote, codemirror, maps, mapslocate, mapsgl);
}

function scripts() {
  main = gulp.src(paths.scripts.main)
    .pipe(concat('galette-main.bundle.min.js'))
    .pipe(uglify({
      output: {
        comments: /^!/
      }
    }))
    .pipe(gulp.dest(paths.assets.js))
    .pipe(browserSync.stream());

  masschanges = gulp.src(paths.scripts.masschanges)
    .pipe(concat('masschanges.min.js'))
    .pipe(uglify({
      output: {
        comments: /^!/
      }
    }))
    .pipe(gulp.dest(paths.assets.js))
    .pipe(browserSync.stream());

  chartjs = gulp.src(paths.scripts.chartjs)
    .pipe(concat('chartjs.min.js'))
    .pipe(gulp.dest(paths.assets.js))
    .pipe(browserSync.stream());

  sortablejs = gulp.src(paths.scripts.sortablejs)
    .pipe(concat('sortable.min.js'))
    .pipe(gulp.dest(paths.assets.js))
    .pipe(browserSync.stream());

  summernote = gulp.src(paths.scripts.summernote)
    .pipe(concat('summernote.min.js'))
    .pipe(gulp.dest(paths.assets.js))
    .pipe(browserSync.stream());

  codemirror = download(paths.scripts.codemirror, { ci: true })
    .pipe(gulp.dest(paths.assets.js))
    .pipe(browserSync.stream());

  codemirrorxml = download(paths.scripts.codemirrorxml, { ci: true })
    .pipe(gulp.dest(paths.assets.js))
    .pipe(browserSync.stream());

  codemirrorformatting = download(paths.scripts.codemirrorformatting, { ci: true })
    .pipe(gulp.dest(paths.assets.js))
    .pipe(browserSync.stream());

  darkreader = gulp.src(paths.scripts.darkreader)
    .pipe(concat('darkreader.min.js'))
    .pipe(uglify({
      output: {
        comments: /^!/
      }
    }))
    .pipe(gulp.dest(paths.assets.js))
    .pipe(browserSync.stream());

  maps = gulp.src(paths.scripts.maps)
    .pipe(concat('maps.min.js'))
    .pipe(uglify({
      output: {
        comments: /^!/
      }
    }))
    .pipe(gulp.dest(paths.assets.js))
    .pipe(browserSync.stream());

  mapslocate = gulp.src(paths.scripts.mapslocate)
    .pipe(concat('maps-locate.min.js'))
    .pipe(gulp.dest(paths.assets.js))
    .pipe(browserSync.stream());

  return merge(main, masschanges, chartjs, sortablejs, summernote, codemirror, codemirrorxml, codemirrorformatting, darkreader, maps, mapslocate);
}

function mapsgl() {
  return Promise.all([
    esbuild.build({
      entryPoints: [paths.mapsgl.entry],
      outfile: paths.assets.js + 'maps-gl.min.js',
      bundle: true,
      minify: true,
      format: 'iife',
      globalName: 'maplibregl',
      alias: paths.mapsgl.aliases
    }),
    esbuild.build({
      entryPoints: [paths.mapsgl.worker],
      outfile: paths.assets.js + 'maps-gl.worker.min.js',
      bundle: true,
      minify: true,
      format: 'esm'
    })
  ]);
}

function movefiles() {
  extras = paths.extras.map(function (extra) {
    const srcOptions = extra.encoding === false ? { encoding: false } : {};

    return gulp.src(extra.src, srcOptions)
      .pipe(gulp.dest(paths.assets.public + extra.dest))
      .pipe(browserSync.stream());
    }
  );

  return merge(extras);
}

function watch() {
  browserSync.init({
    proxy: localServer.url
  })

  gulp.watch([paths.src.favicon, paths.src.logo, paths.src.photo], gulp.series(galette)).on('change', browserSync.reload)
  gulp.watch([paths.src.semantic], gulp.series(theme, 'build ui')).on('change', browserSync.reload)
  gulp.watch([paths.src.theme, paths.src.config], gulp.series(theme, 'build-css')).on('change', browserSync.reload)
  gulp.watch([paths.src.css], gulp.series(styles)).on('change', browserSync.reload)
  gulp.watch([paths.src.js], gulp.series(scripts)).on('change', browserSync.reload)
  gulp.watch(['./ui/js/maps/*.js'], gulp.series(mapsgl)).on('change', browserSync.reload)
  gulp.watch([paths.assets.theme.css, paths.assets.theme.js, paths.assets.theme.images]).on('change', browserSync.reload)
}

exports.galette = galette;
exports.theme = theme;
exports.clean = clean;
exports.styles = styles;
exports.scripts = scripts;
exports.mapsgl = mapsgl;
exports.movefiles = movefiles;
exports.watch = watch;

var build = gulp.series(theme, clean, styles, scripts, mapsgl, movefiles, 'build ui', galette);
exports.default = build;
