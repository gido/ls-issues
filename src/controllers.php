<?php

use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;
use \DateTime;

$app->match('/', function() use ($app) {

    $repositories = $app['github']->api('organization')->repositories('Antistatique');

    return $app['twig']->render('index.html.twig', array(
        'repositories' => $repositories,
    ));

})->bind('homepage');

$app->match('/issues/{repo}', function(Request $request, $repo) use ($app) {

    list($username, $repository) = explode('/', $repo);

    $from = new \DateTime($request->query->get('from', '1 month ago'));
    $to = new \DateTime($request->query->get('to', 'now'));
    $page = $request->query->get('p', 1);

    $options = array(
        'state' => 'closed',
        'sort' => 'created',
        'direction' => 'asc',
        'since' => $from->format(\DateTime::ISO8601),
        'per_page' => 100,
        'page' => $page,
    );

    $issues = $app['github']->api('issue')->all($username, $repository, $options);

    $issues = array_filter($issues, function($issue) use ($from, $to) {
        $closedAt = new DateTime($issue['closed_at']);
        return ($closedAt >= $from && $closedAt <= $to);
    });

    // sort by closed date
    usort($issues, function($a, $b) {
        $AclosedAt = new DateTime($a['closed_at']);
        $BclosedAt = new DateTime($b['closed_at']);

        if ($AclosedAt == $BclosedAt) {
            return 0;
        }

        return ($AclosedAt < $BclosedAt) ? -1 : 1;
    });

    foreach($issues as &$issue) {
        $issue['body_estimate'] = '';
        if ($issue['body'] && preg_match('/\-{2,3}(.*)$/ms', $issue['body'], $matches)) {
            $issue['body_estimate'] = $matches[1];
        }
    }

    $repo = $app['github']->api('repo')->show($username, $repository);

    return $app['twig']->render('issues.html.twig', array(
         'issues' => $issues,
         'repo' => $repo,
         'from' => $from,
         'to' => $to,
     ));

})
->bind('issues')
->assert('repo', '^[a-zA-Z0-9\-\._]+\/[a-zA-Z0-9\.\-_]+$');

$app->match('/login', function(Request $request) use ($app) {
    $form = $app['form.factory']->createBuilder('form')
        ->add('username', 'text', array('label' => 'Username', 'data' => $app['session']->get('_security.last_username')))
        ->add('password', 'password', array('label' => 'Password'))
        ->getForm()
    ;

    return $app['twig']->render('login.html.twig', array(
        'form'  => $form->createView(),
        'error' => $app['security.last_error']($request),
    ));
})->bind('login');


$app->match('/logout', function() use ($app) {
    $app['session']->clear();

    return $app->redirect($app['url_generator']->generate('homepage'));
})->bind('logout');

$app->error(function (\Exception $e, $code) use ($app) {
    if ($app['debug']) {
        return;
    }

    switch ($code) {
        case 404:
            $message = 'The requested page could not be found.';
            break;
        default:
            $message = 'We are sorry, but something went terribly wrong.';
    }

    return new Response($message, $code);
});

return $app;
