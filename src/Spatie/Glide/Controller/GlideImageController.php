<?php namespace Spatie\Glide\Controller;

use Illuminate\Routing\Controller;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use League\Glide\Http\SignatureFactory;
use League\Glide\Server;
use Illuminate\Foundation\Application;
use Spatie\Glide\GlideApiFactory;

class GlideImageController extends Controller {

    protected $app;
    protected $request;
    protected $glideConfig;
    protected $disk;

    /**
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->glideConfig = $this->app['config']->get('laravel-glide::config');
        $this->request = $this->app['request'];
    }

    /**
     * Output a generated Glide-image
     */
    public function index($disk = null)
    {
        $this->setDisk($disk);

        $this->validateSignature();

        $this->writeIgnoreFile();

        $api = GlideApiFactory::create();

        $server = $this->setGlideServer($this->setImageSource(), $this->setImageCache(), $api);

        return $server->outputImage($this->request);
    }

    /**
     * Validates the signature if useSecureURLs in enabled
     */
    protected function validateSignature()
    {
        foreach($this->request->all() as $parameter => $value) {
            if(empty($value) === true) {
                $this->request->query->remove($parameter);
            }
        }

        if($this->glideConfig['useSecureURLs']) {
            SignatureFactory::create($this->app['config']->get('app.key'))
                ->validateRequest($this->request);
        }
    }

    /**
     *  Set the source path for images
     *
     * @return Filesystem
     */
    protected function setImageSource()
    {
        if ($this->disk && $this->app->bound('flysystem')) {
            $filesystem = app('flysystem')->connection($this->disk);
        } else {
            $filesystem = (new Filesystem(new Local(
                $this->glideConfig['source']['path']
            )));
        }
        return $filesystem;
    }

    /**
     * Set the cache folder
     *
     * @return Filesystem
     */
    protected function setImageCache()
    {
        return (new Filesystem(new Local(
            $this->glideConfig['cache']['path'] . DIRECTORY_SEPARATOR . $this->disk
        )));
    }

    /**
     * Set the flysystem disk
     *
     */
    protected function setDisk($disk)
    {
        $disks = $this->glideConfig['disks'];

        if (!empty($disks) && in_array($disk, $disks)) {
            $this->disk = $disk;
        } else {
            $this->disk = "";
        }
    }

    /**
     * Configure the Glide Server with the baseURL
     *
     * @param $source
     * @param $cache
     * @param $api
     *
     * @return Server
     */
    protected function setGlideServer($source, $cache, $api)
    {
        $server = new Server($source, $cache,$api);

        $server->setBaseUrl($this->glideConfig['baseURL'] . DIRECTORY_SEPARATOR . $this->disk);

        return $server;
    }

    /**
     * Copy the gitignore stub to the given directory
     */
    public function writeIgnoreFile()
    {
        $this->createCacheFolder();

        $destinationFile = $this->glideConfig['cache']['path'].'/.gitignore';

        if (!file_exists($destinationFile)) {
            $this->app['files']->copy(__DIR__.'/../../../stubs/gitignore.txt', $destinationFile);
        }
    }

    private function createCacheFolder()
    {
        if( ! is_dir($this->glideConfig['cache']['path']))
        {
            mkdir($this->glideConfig['cache']['path'], 0777, true);
        }
    }
}
