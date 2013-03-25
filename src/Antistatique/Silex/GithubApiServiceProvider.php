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
            
            $client = new Github\Client($app['github.client']);

            if (isset($app['github.auth_method']) && $app['github.auth_method']) {
                switch($app['github.auth_method']) {
                    case 'http_auth':
                        $authMethod = Github\Client::AUTH_HTTP_PASSWORD;
                        $usernameOrToken = $app['github.username'];
                        $secret = $app['github.password'];
                        break;
                    default:
                        $authMethod = null;
                        $usernameOrToken = null;
                        $secret = null;
                }

                $client->authenticate($usernameOrToken, $secret, $authMethod);
            }

            return $client;
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
