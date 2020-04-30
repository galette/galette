/*******************************
 * Install files removed on git
 *******************************/

var fs = require('fs-extra'); // Used for recursive copying
var path = require('path');

var _missing = [
    'src/definitions',
    'src/semantic.less',
    'src/theme.less',
    'src/themes',
    'tasks'
];

_missing.forEach(function (item){
    try {
        var stat = fs.statSync(path.join(__dirname, './', item));
        console.log('\'' + item + '\' folder already exists. Continuing.')
    } catch (e) {
        console.log('Copying \'' + item + '\' folder from \'node_modules/fomantic-ui/' + item + '\'');
        fs.copySync(path.join(__dirname, '../node_modules/fomantic-ui/', item), path.join(__dirname, './', item));
        console.log('Copying done! Continuing.');
    }
});

/*******************************
 *           Set-up
 *******************************/

var
  gulp   = require('gulp'),

  // read user config to know what task to load
  config = require('./tasks/config/user')
;


/*******************************
 *            Tasks
 *******************************/

require('./tasks/collections/build')(gulp);
require('./tasks/collections/install')(gulp);

gulp.task('default', gulp.series('watch'));

/*--------------
      Docs
---------------*/

require('./tasks/collections/docs')(gulp);

/*--------------
      RTL
---------------*/

if (config.rtl) {
  require('./tasks/collections/rtl')(gulp);
}
