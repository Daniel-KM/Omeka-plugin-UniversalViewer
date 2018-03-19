'use strict';

var async = require('async');
var del = require('del');
var fs = require('fs');
var glob = require("glob");
var gulp = require('gulp');
var rename = require("gulp-rename");

gulp.task('clean', function(done) {
    return del('views/shared/javascripts/uv');
});

gulp.task('sync', function(done) {
    async.series([
        function (next) {
            gulp.src(['node_modules/universalviewer/dist/uv-*/**'])
            .pipe(gulp.dest('views/shared/javascripts/'))
            .on('end', next);
        }
    ], done);
});

gulp.task('rename', function(done) {
    var file = glob.sync('views/shared/javascripts/uv-*/');
    fs.renameSync(file[0], 'views/shared/javascripts/uv/');
    done();
});

gulp.task('default', gulp.series('clean', 'sync', 'rename'));

gulp.task('install', gulp.task('default'));

gulp.task('update', gulp.task('default'));
