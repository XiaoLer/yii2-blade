<?php
/**
 * Author : Scholer <scholer_l@live.com>
 * date   : 2015-10-04
 */
namespace xiaoler\blade;

use Yii;
use yii\web\View;
use yii\base\Widget;
use yii\base\InvalidConfigException;
use yii\base\ViewRenderer as BaseViewRenderer;

use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Engines\PhpEngine;
use Illuminate\View\Engines\CompilerEngine;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\FileViewFinder;
use Illuminate\View\Factory as Blade;

class ViewRenderer extends BaseViewRenderer
{
    protected $blade;

    protected $extensions = ['bl' => 'blade'];

    protected $cachePath = '@runtime/Blade/cache';

    protected $viewPath = ['@app/views', '@yii/views', '@vendor/yiisoft/yii2-debug/views'];

    public function init()
    {
        $container = $this->getContainer();
        $resolver = $container['view.engine.resolver'];
        $finder = $container['view.finder'];
        $events = $container['events'];

        $this->blade = new Blade($resolver, $finder, $events);
        $this->blade->setContainer($container);

        foreach ($this->extensions as $ext => $engine) {
            $this->blade->addExtension($ext, $engine);
        }
    }

    public function render($view, $file, $params)
    {
        foreach ($this->viewPath as $path) {
            if (strncmp($path, '@', 1) === 0) {
                $path = Yii::getAlias($path);
            }
            if (strpos($file, $path) === 0) {
                $file = str_replace($path, '', $file);
                break;
            }
        }
        $file = $this->trimFileExt($file);

        $params['app'] = \Yii::$app;
        $params['view'] = $view;
        return $this->blade->make($file, $params)->render();
    }

    private function getContainer()
    {
        $cache = Yii::getAlias($this->cachePath);
        $views = array_map(function ($alias) {
            return Yii::getAlias($alias);
        }, $this->viewPath);

        $container = new Container;

        $container->bindShared('files', function () {
            return new Filesystem;
        });
        $container->bindShared('events', function () {
            return new Dispatcher;
        });
        $container->bindShared('view.finder', function ($app) use ($views) {
            return new FileViewFinder($app['files'], $views);
        });
        $container->bindShared('blade.compiler', function ($app) use ($cache) {
            return new BladeCompiler($app['files'], $cache);
        });
        $container->bindShared('view.engine.resolver', function () use ($cache, $container) {
            $resolver = new EngineResolver;
            $resolver->register('php', function () {
                return new PhpEngine;
            });
            $resolver->register('blade', function () use ($container) {
                return new CompilerEngine($container['blade.compiler'], $container['files']);
            });
            return $resolver;
        });

        return $container;
    }

    private function trimFileExt($file)
    {
        $extensions = array_keys($this->blade->getExtensions());
        usort($extensions, function ($a, $b) {
            $diff = strlen($a) - strlen($b);
            if ($diff == 0) {
                return 0;
            }
            return $diff > 0 ? -1 : 1;
        });
        foreach ($extensions as $ext) {
            $ext = '.' . $ext;
            $length = strlen($ext);
            if (substr($file, -$length) === $ext) {
                return substr_replace($file, '', -$length);
            }
        }
        return $file;
    }
}