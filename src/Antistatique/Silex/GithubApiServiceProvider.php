<?php

namespace Antistatique\Silex;

use Silex\Application;
use Silex\ServiceProviderInterface;

use Github;

/**
 * Github API Provider
 *
 * @author Gilles Doge <gilles@antistatique.net>
 */
class GithubApiServiceProvider implements ServiceProviderInterface
{
    public function register(Application $app)
    {
        $app['github'] = $app->share(function ($app) {
            

            return new Github\Client($app['github.client']);
        });

        $app['github.client'] = $app->share(function ($app) {

            if ($app['github.cache'] && $app['github.cache_dir']) {
                return new Github\HttpClient\CachedHttpClient(array('cache_dir' => $app['github.cache_dir']));
            }

            return null;
        });
    }

    public function boot(Application $app)
    {
    }
}
