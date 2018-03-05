<?php
/**
 * Statify Robo build script.
 *
 * This file contains the Robo tasks for building a distributable plugin package.
 * Should not be included in final package.
 *
 * @package   Statify
 */

use Robo\Exception\TaskException;
use Robo\Tasks;
use Symfony\Component\Finder\Finder;

/**
 * Class RoboFile
 */
class RoboFile extends Tasks {
    const PROJECT_NAME = 'statify';
    const SVN_URL = 'https://plugins.svn.wordpress.org/statify';

    const OPT_TARGET = 'target';
    const OPT_SKIPSTYLE = 'skipStyle';

    /**
     * Version tag (read from composer.json).
     *
     * @var string
     */
    private $version;

    /**
     * Target directory path.
     *
     * @var string
     */
    private $target_dir;

    /**
     * Final package name.
     *
     * @var string
     */
    private $final_name;

    /**
     * RoboFile constructor
     *
     * @param array $opts Options.
     *
     * @return void
     */
    public function __construct( $opts = [ self::OPT_TARGET => 'dist' ] ) {
        // Read composer configuration and extract version number..
        $composer = json_decode( file_get_contents( __DIR__ . '/composer.json' ) );
        // Extract parameter from options.
        $this->version    = '1.6.0'; //$composer->version;
        $this->target_dir = $opts[ self::OPT_TARGET ];
        $this->final_name = self::PROJECT_NAME . '.' . $this->version;
    }

    /**
     * Clean up target directory
     *
     * @param array $opts Options.
     *
     * @return void
     */
    public function clean( $opts = [ self::OPT_TARGET => 'dist' ] ) {
        $this->say( 'Cleaning target directory...' );
        if ( is_dir( $this->target_dir . '/' . $this->final_name ) ) {
            $this->_deleteDir( [ $this->target_dir . '/' . $this->final_name ] );
        }
        if ( is_file( $this->target_dir . '/' . $this->final_name . '.zip' ) ) {
            $this->_remove( $this->target_dir . '/' . $this->final_name . '.zip' );
        }
    }

    /**
     * Run code style tests
     *
     * @return void
     */
    public function testCS() {
        $this->say( 'Executing PHPCS tests...' );
        $this->_exec( __DIR__ . '/vendor/bin/phpcs --standard=phpcs.xml -s' );
    }

    /**
     * Minify JavaScript and CSS assets in target director.
     *
     * @return void
     */
    public function minify() {
        $this->minifyJS();
        $this->minifyCSS();
    }

    /**
     * Minify CSS assets.
     *
     * @return void
     */
    public function minifyCSS() {
        $this->say('Minifying CSS...');

        $finder = Finder::create()->name('*.css*')
                        ->notName('*.min.css')
                        ->in($this->target_dir . '/' .$this->final_name . '/css');
        foreach ($finder as $file) {
            $this->taskMinify($file)
                 ->run();
        }
    }

    /**
     * Minify JavaScript assets.
     *
     * @return void
     */
    public function minifyJS() {
        $this->say('Minifying JavaScript...');

        $finder = Finder::create()->name('*.js*')
                        ->notName('*.min.js')
                        ->in($this->target_dir . '/' .$this->final_name . '/js');
        foreach ($finder as $file) {
            $this->taskMinify($file)->run();
        }
    }

    /**
     * Build a distributable bundle.
     *
     * @return void
     */
    public function build(
        $opts = [
            self::OPT_TARGET    => 'dist',
            self::OPT_SKIPSTYLE => false,
        ]
    ) {
        $this->clean( $opts );
        if ( isset( $opts[ self::OPT_SKIPSTYLE ] ) && true === $opts[ self::OPT_SKIPSTYLE ] ) {
            $this->say( 'Style checks skipped' );
        } else {
            $this->testCS();
        }
        $this->bundle();
        $this->minify();
    }

    /**
     * Bundle global resources.
     *
     * @return void
     */
    private function bundle() {
        $this->say( 'Bundling resources...' );
        $this->taskCopyDir( [
                                'css'   => $this->target_dir . '/' . $this->final_name . '/css',
                                'inc'   => $this->target_dir . '/' . $this->final_name . '/inc',
                                'js'   => $this->target_dir . '/' . $this->final_name . '/js',
                                'views' => $this->target_dir . '/' . $this->final_name . '/views',
                            ] )->run();
        $this->_copy( 'statify.php', $this->target_dir . '/' . $this->final_name . '/statify.php' );
        $this->_copy( 'readme.txt', $this->target_dir . '/' . $this->final_name . '/readme.txt' );
        $this->_copy( 'LICENSE', $this->target_dir . '/' . $this->final_name . '/LICENSE' );

        // Third-party dependencies.
        $this->_copy( 'vendor/npm-asset/chartist/dist/chartist.min.css', $this->target_dir . '/' . $this->final_name . '/css/chartist.min.css' );
        $this->_copy( 'vendor/npm-asset/chartist/dist/chartist.min.js', $this->target_dir . '/' . $this->final_name . '/js/chartist.min.js' );
        $this->_copy( 'vendor/npm-asset/chartist-plugin-tooltips/dist/chartist-plugin-tooltip.css', $this->target_dir . '/' . $this->final_name . '/css/chartist-plugin-tooltip.css' );
        $this->_copy( 'vendor/npm-asset/chartist-plugin-tooltips/dist/chartist-plugin-tooltip.min.js', $this->target_dir . '/' . $this->final_name . '/js/chartist-plugin-tooltip.min.js' );
    }

    /**
     * Create ZIP package from distribution bundle.
     *
     * @param array $opts Options.
     *
     * @return void
     */
    public function package(
        $opts = [
            self::OPT_TARGET    => 'dist',
            self::OPT_SKIPSTYLE => false,
        ]
    ) {
        $this->build( $opts );
        $this->say( 'Packaging...' );
        $this->taskPack( $this->target_dir . '/' . $this->final_name . '.zip' )
             ->addDir( '', $this->target_dir . '/' . $this->final_name )
             ->run();
    }

    /**
     * Deploy development version (trunk).
     *
     * @param array $opts Options.
     *
     * @return void
     * @throws TaskException On errors.
     */
    public function deployTrunk(
        $opts = [
            self::OPT_TARGET    => 'dist',
            self::OPT_SKIPSTYLE => false,
        ]
    ) {
        // First execute build job.
        $this->build( $opts );

        // Prepare VCS, either checkout or update local copy.
        $this->prepareVCS();

        $this->say( 'Preparing deployment directory...' );
        $this->updateVCStrunk();

        // Update remote repository.
        $this->say( 'Deploying...' );
        $this->commitVCS(
            '--force trunk/*',
            'Updated ' . self::PROJECT_NAME . ' trunk'
        );
    }

    /**
     * Deploy current version tag.
     *
     * @param array $opts Options.
     *
     * @return void
     * @throws TaskException On errors.
     */
    public function deployTag(
        $opts = [
            self::OPT_TARGET    => 'dist',
            self::OPT_SKIPSTYLE => false,
        ]
    ) {
        // First execute build job.
        $this->build( $opts );

        // Prepare VCS, either checkout or update local copy.
        $this->prepareVCS();

        $this->say( 'Preparing deployment directory...' );
        $this->updateVCStag();

        // Update remote repository.
        $this->say( 'Deploying...' );
        $this->commitVCS(
            'tags/' . $this->version,
            'Updated ' . self::PROJECT_NAME . ' v' . $this->version
        );
    }

    /**
     * Deploy current version tag.
     *
     * @param array $opts Options.
     *
     * @return void
     * @throws TaskException On errors.
     */
    public function deployReadme(
        $opts = [
            self::OPT_TARGET    => 'dist',
            self::OPT_SKIPSTYLE => false,
        ]
    ) {
        // First execute build job.
        $this->build( $opts );

        // Prepare VCS, either checkout or update local copy.
        $this->prepareVCS();

        $this->updateVCSreadme();

        // Update remote repository.
        $this->say( 'Deploying...' );
        $this->commitVCS(
            '--force trunk/readme.txt',
            'Updated ' . self::PROJECT_NAME . ' ReadMe'
        );
    }

    /**
     * Deploy current version tag and trunk.
     *
     * @param array $opts Options.
     *
     * @return void
     * @throws TaskException On errors.
     */
    public function deployAll(
        $opts = [
            self::OPT_TARGET    => 'dist',
            self::OPT_SKIPSTYLE => false,
        ]
    ) {
        // First execute build job.
        $this->build( $opts );

        // Prepare VCS, either checkout or update local copy.
        $this->prepareVCS();

        $this->say( 'Preparing deployment directory...' );
        $this->updateVCStrunk();
        $this->updateVCStag();

        // Update remote repository.
        $this->say( 'Deploying...' );
        $this->commitVCS(
            [
                '--force trunk/*',
                '--force tags/' . $this->version,
            ],
            'Updated ' . self::PROJECT_NAME . ' v' . $this->version
        );
    }

    /**
     * Prepare VCS direcory.
     *
     * Checkout or update local copy of SVN repository.
     *
     * @return void
     * @throws TaskException On errors.
     */
    private function prepareVCS() {
        if ( is_dir( $this->target_dir . '/svn' ) ) {
            $this->taskSvnStack()
                 ->stopOnFail()
                 ->dir( $this->target_dir . '/svn/statify' )
                 ->update()
                 ->run();
        } else {
            $this->_mkdir( $this->target_dir . '/svn' );
            $this->taskSvnStack()
                 ->dir( $this->target_dir . '/svn' )
                 ->checkout( self::SVN_URL )
                 ->run();
        }
    }

    /**
     * Commit VCS changes
     *
     * @param string|array $to_add Files to add.
     * @param string       $msg    Commit message.
     *
     * @return void
     * @throws TaskException On errors.
     */
    private function commitVCS( $to_add, $msg ) {
        $task = $this->taskSvnStack()
                     ->stopOnFail()
                     ->dir( $this->target_dir . '/svn/statify' );

        if ( is_array( $to_add ) ) {
            foreach ( $to_add as $ta ) {
                $task = $task->add( $ta );
            }
        } else {
            $task = $task->add( $to_add );
        }

        $task->commit( $msg )->run();
    }

    /**
     * Update SVN readme file.
     *
     * @return void
     */
    private function updateVCSreadme() {
        $trunk_dir = $this->target_dir . '/svn/statify/trunk';
        $this->_copy( $this->target_dir . '/' . $this->final_name . '/readme.txt', $trunk_dir . '/readme.txt' );
    }

    /**
     * Update SVN development version (trunk).
     *
     * @return void
     */
    private function updateVCStrunk() {
        // Clean trunk directory.
        $trunk_dir = $this->target_dir . '/svn/statify/trunk';
        $this->taskCleanDir( $trunk_dir )->run();

        // Copy built bundle to trunk.
        $this->taskCopyDir( [ $this->target_dir . '/' . $this->final_name => $trunk_dir ] )->run();
    }

    /**
     * Update current SVN version tag.
     *
     * @return void
     */
    private function updateVCStag() {
        // Clean tag directory if it exists.
        $tag_dir = $this->target_dir . '/svn/statify/tags/' . $this->version;
        if ( is_dir( $tag_dir ) ) {
            $this->taskCleanDir( $this->target_dir . '/svn/statify/tags/' . $this->version )->run();
        } else {
            $this->_mkdir( $tag_dir );
        }

        // Copy built bundle to trunk.
        $this->taskCopyDir( [ $this->target_dir . '/' . $this->final_name => $tag_dir ] )->run();
    }
}