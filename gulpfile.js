var async = require('async');
var del = require('del');
var fs = require('fs');
var glob = require("glob");
var gulp = require('gulp');
var rename = require("gulp-rename");
var runSequence = require('run-sequence');

gulp.task('sync', function(cb) {

    async.series([
        function (next) {
            gulp.src(['node_modules/universalviewer/dist/uv-*/**'])
            .pipe(gulp.dest('views/shared/javascripts/'))
            .on('end', next);
        }
    ], cb);
});

gulp.task('clean', function(cb) {
    return del('views/shared/javascripts/uv');
});

gulp.task('rename', function(cb) {
    var file = glob.sync('views/shared/javascripts/uv-*/');
    fs.renameSync(file[0], 'views/shared/javascripts/uv/');
    cb();
});

gulp.task('default', function(cb) {
    runSequence('clean', 'sync', 'rename', cb);
});