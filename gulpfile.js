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
  browserSync = require('browser-sync').create(),
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
    logo: './ui/images/galette.png',
    photo:'./ui/images/default.png'
  },
  semantic: {
    src: './semantic/src/',
    theme: './semantic/src/themes/galette/'
  },
  styles: {
    main: [
      './ui/css/galette.css'
    ],
    summernote: [
      './node_modules/summernote/dist/summernote-lite.min.css'
    ]
  },
  scripts: {
    main: [
      './node_modules/js-cookie/dist/js.cookie.js',
      './ui/js/common.js'
    ],
    masschanges: [
      './ui/js/masschanges.js'
    ],
    chartjs: [
      './node_modules/chart.js/dist/chart.min.js',
      './node_modules/chartjs-plugin-autocolors/dist/chartjs-plugin-autocolors.min.js',
      './node_modules/chartjs-plugin-datalabels/dist/chartjs-plugin-datalabels.min.js'
    ],
    sortablejs: [
      './node_modules/sortablejs/Sortable.min.js'
    ],
    summernote: [
      './node_modules/summernote/dist/summernote-lite.min.js'
    ]
  },
  extras: [
    {
      src: './ui/css/install.css',
      dest: 'css/'
    }, {
      src: './node_modules/summernote/dist/font/*',
      dest: 'webfonts/'
    }, {
      src: './node_modules/summernote/dist/lang/*.min.js',
      dest: 'js/lang/'
    }, {
      src: './node_modules/jquery/dist/jquery.min.js',
      dest: 'js/'
    }
  ]
};

function galette() {
  favicon = gulp.src(paths.src.favicon)
    .pipe(gulp.dest(paths.assets.theme.images))
    .pipe(browserSync.stream());

  logo =  gulp.src(paths.src.logo)
    .pipe(gulp.dest(paths.assets.theme.images))
    .pipe(browserSync.stream());

  photo =  gulp.src(paths.src.photo)
    .pipe(gulp.dest(paths.assets.theme.images))
    .pipe(browserSync.stream());

  return merge(favicon, logo, photo);
}

function theme() {
  config = gulp.src(paths.src.config)
    .pipe(gulp.dest(paths.semantic.src))
    .pipe(browserSync.stream());

  theme =  gulp.src(paths.src.files)
    .pipe(gulp.dest(paths.semantic.theme))
    .pipe(browserSync.stream());

  return merge(config, theme);
}

function clean() {
  return del([
    paths.assets.public,
    paths.assets.theme.public,
  ]);
}

function styles() {
  main = gulp.src(paths.styles.main)
    .pipe(cleanCSS())
    .pipe(concat('galette-main.bundle.min.css'))
    .pipe(gulp.dest(paths.assets.css))
    .pipe(browserSync.stream());

  summernote = gulp.src(paths.styles.summernote)
    .pipe(replace('url(font/', 'url(../webfonts/'))
    .pipe(cleanCSS())
    .pipe(concat('summernote.min.css'))
    .pipe(gulp.dest(paths.assets.css))
    .pipe(browserSync.stream());

  return merge(main, summernote);
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

  return merge(main, masschanges, chartjs, sortablejs, summernote);
}

function extras() {
  main = paths.extras.map(function (extra) {
    return gulp.src(extra.src)
      .pipe(gulp.dest(paths.assets.public + extra.dest))
      .pipe(browserSync.stream());
    }
  );

  return merge(main);
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
  gulp.watch([paths.assets.theme.css, paths.assets.theme.js, paths.assets.theme.images]).on('change', browserSync.reload)
}

exports.galette = galette;
exports.theme = theme;
exports.clean = clean;
exports.styles = styles;
exports.scripts = scripts;
exports.extras = extras;
exports.watch = watch;

var build = gulp.series(theme, clean, styles, scripts, extras, 'build ui', galette);
exports.default = build;
