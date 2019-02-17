'use strict';

const gulp = require('gulp');
const del = require('del');
const fs = require('fs');
const glob = require("glob");
const rename = require("gulp-rename");
const replace = require('gulp-string-replace');

const bundle = [
    {
        'source': 'node_modules/universalviewer/dist/uv-*/**',
        'dest': 'views/shared/javascripts/uv',
    },
];

gulp.task('clean', function(done) {
    bundle.forEach(function (module) {
        return del.sync(module.dest);
    });
    done();
});

gulp.task('sync', function (done) {
    bundle.forEach(function (module) {
        gulp.src(module.source)
            .pipe(gulp.dest(module.dest))
            .on('end', done);
    });
});

// The dist is unknown, so it should be renamed.
const rename_uv = function(done) {
    var file = glob.sync('views/shared/javascripts/uv/uv-*/');
    fs.renameSync(file[0], 'views/shared/javascripts/uv_dist/');
    del.sync('views/shared/javascripts/uv/');
    fs.renameSync('views/shared/javascripts/uv_dist/', 'views/shared/javascripts/uv/');
    done();
};

// Avoid a warning for unknown charset in a iframe.
const hack_uv = function (done) {
    gulp.src(['views/shared/javascripts/uv/app.html'])
        .pipe(replace(
            /^<head>$/gm,
            '<head>' + "\n" + '    <meta charset="UTF-8">'
        ))
        .pipe(gulp.dest('views/shared/javascripts/uv/'))
        .on('end', done);
};

gulp.task('default', gulp.series('clean', 'sync', rename_uv, hack_uv));

gulp.task('install', gulp.task('default'));

gulp.task('update', gulp.task('default'));
