module.exports = function (grunt) {

    var prod = grunt.option('build') === 'production',
        uat = grunt.option('env') === 'uat';

    grunt.initConfig({
        pkg: grunt.file.readJSON('package.json'),
        // General options
        opts: {
            project: 'Luciditi Age Assurance',
            website: 'https://luciditi.co.uk/',
            supportURL: 'https://luciditi.co.uk/age-assurance',
            pluginFile: 'luciditi-age-assurance.php',
            pluginSlug: 'luciditi-age-assurance',
        },
        // Setting folder templates.
        dirs: {
            css: 'includes/assets/css',
            js: 'includes/assets/js',
        },
        // Remove previously minified files & any related temp files
        clean: {
            css: [
                '<%= dirs.css %>/min/*.css',
                '<%= dirs.css %>/min/*.css.map',
            ],
            js: [
                '<%= dirs.js %>/min/*.js',
                '<%= dirs.js %>/min/*.js.map',
            ]
        },
        // Minify JavaScript
        uglify: {
            options: {
                sourceMap: !prod,
                sourceMapIncludeSources: !prod,
                banner: '/* (c) <%= grunt.template.today("yyyy") %> <%= opts.project %> - <%= opts.website %> */\n',
                report: 'min',
                compress: {
                    drop_console: false,
                    sequences: false
                }
            },
            scripts: {
                files: {
                    '<%= dirs.js %>/min/admin.min.js': [
                        '<%= dirs.js %>/admin.js',
                    ],
                    '<%= dirs.js %>/min/public.min.js': [
                        '<%= dirs.js %>/public.js',
                    ],
                },
            }
        },
        cssmin: {
            options: {
                sourceMap: !prod,
                sourceMapInlineSources: !prod,
                mergeIntoShorthands: false,
                roundingPrecision: -1,
                // noAdvanced: true,
                rebase: true,
                // rebaseTo: '<%= yeoman.client %>'
            },
            styles: {
                files: {
                    '<%= dirs.css %>/min/public.min.css': [
                        '<%= dirs.css %>/normalize.css',
                        '<%= dirs.css %>/public.css',
                    ],
                    '<%= dirs.css %>/min/admin.min.css': [
                        '<%= dirs.css %>/admin.css',
                    ],
                }
            }
        },
        // Autoprefixer.
        postcss: {
            options: {
                processors: [
                    require('autoprefixer')(),
                ]
            },
            dist: {
                src: [
                    '<%= dirs.css %>/min/public.min.css',
                    '<%= dirs.css %>/min/admin.min.css',
                ]
            }
        },
        // Watch changes and run tasks
        watch: {
            css: {
                files: [
                    '<%= dirs.css %>/public.css',
                    '<%= dirs.css %>/admin.css',
                ],
                tasks: ['css']
            },
            js: {
                files: [
                    '<%= dirs.js %>/public.js',
                    '<%= dirs.js %>/admin.js',
                ],
                tasks: ['js']
            },
        },
        // Generate pot file
        makepot: {
            target: {
                options: {
                    cwd: '',
                    domainPath: 'languages',
                    exclude: [],
                    include: [],
                    mainFile: '<%= opts.pluginFile %>',
                    potComments: '',
                    potFilename: '<%= opts.pluginSlug %>.pot',
                    potHeaders: {
                        poedit: true,
                        'x-poedit-keywordslist': true,
                        'Report-Msgid-Bugs-To': '<%= opts.supportURL %>'
                    },
                    processPot: null,
                    type: 'wp-plugin',
                    updateTimestamp: true,
                    updatePoFiles: false
                }
            }
        },
        // Switch between production and development API URLs
        replace: {
            api_urls: {
                src: ['luciditi-age-assurance.php'],
                overwrite: true,                 // overwrite matched source files
                replacements: [{
                    from: prod && !uat ? 'https://sdk-uat3.luciditi-api.net' : 'https://sdk-live3.luciditi-api.net',
                    to: prod && !uat ? 'https://sdk-live3.luciditi-api.net' : 'https://sdk-uat3.luciditi-api.net'
                }]
            }
        }
    });


    grunt.loadNpmTasks('grunt-contrib-clean');
    grunt.loadNpmTasks('grunt-contrib-uglify');
    grunt.loadNpmTasks('grunt-contrib-cssmin');
    grunt.loadNpmTasks('@lodder/grunt-postcss');
    grunt.loadNpmTasks('grunt-contrib-watch');
    grunt.loadNpmTasks('grunt-wp-i18n');
    grunt.loadNpmTasks('grunt-text-replace');

    // Uglify tasks
    grunt.registerTask('js', ['clean:js', 'uglify']);
    // CSSMin tasks
    grunt.registerTask('css', ['clean:css', 'cssmin', 'postcss']);
    // Language tasks
    grunt.registerTask('lang', ['makepot']);
    // Replace & string related tasks
    grunt.registerTask('strings', ['replace']);
    // Register Default tasks.
    grunt.registerTask('default', [
        'js',
        'css',
        'makepot',
        'strings',
    ]);

};