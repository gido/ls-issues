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

$app->match('/form', function(Request $request) use ($app) {

    $builder = $app['form.factory']->createBuilder('form');
    $choices = array('choice a', 'choice b', 'choice c');

    $form = $builder
        ->add(
            $builder->create('sub-form', 'form')
                ->add('subformemail1', 'email', array(
                    'constraints' => array(new Assert\NotBlank(), new Assert\Email()),
                    'attr'        => array('placeholder' => 'email constraints'),
                    'label'       => 'A custome label : ',
                ))
                ->add('subformtext1', 'text')
        )
        ->add('text1', 'text', array(
            'constraints' => new Assert\NotBlank(),
            'attr'        => array('placeholder' => 'not blank constraints')
        ))
        ->add('text2', 'text', array('attr' => array('class' => 'span1', 'placeholder' => '.span1')))
        ->add('text3', 'text', array('attr' => array('class' => 'span2', 'placeholder' => '.span2')))
        ->add('text4', 'text', array('attr' => array('class' => 'span3', 'placeholder' => '.span3')))
        ->add('text5', 'text', array('attr' => array('class' => 'span4', 'placeholder' => '.span4')))
        ->add('text6', 'text', array('attr' => array('class' => 'span5', 'placeholder' => '.span5')))
        ->add('text8', 'text', array('disabled' => true, 'attr' => array('placeholder' => 'disabled field')))
        ->add('textarea', 'textarea')
        ->add('email', 'email')
        ->add('integer', 'integer')
        ->add('money', 'money')
        ->add('number', 'number')
        ->add('password', 'password')
        ->add('percent', 'percent')
        ->add('search', 'search')
        ->add('url', 'url')
        ->add('choice1', 'choice',  array(
            'choices'  => $choices,
            'multiple' => true,
            'expanded' => true
        ))
        ->add('choice2', 'choice',  array(
            'choices'  => $choices,
            'multiple' => false,
            'expanded' => true
        ))
        ->add('choice3', 'choice',  array(
            'choices'  => $choices,
            'multiple' => true,
            'expanded' => false
        ))
        ->add('choice4', 'choice',  array(
            'choices'  => $choices,
            'multiple' => false,
            'expanded' => false
        ))
        ->add('country', 'country')
        ->add('language', 'language')
        ->add('locale', 'locale')
        ->add('timezone', 'timezone')
        ->add('date', 'date')
        ->add('datetime', 'datetime')
        ->add('time', 'time')
        ->add('birthday', 'birthday')
        ->add('checkbox', 'checkbox')
        ->add('file', 'file')
        ->add('radio', 'radio')
        ->add('password_repeated', 'repeated', array(
            'type'            => 'password',
            'invalid_message' => 'The password fields must match.',
            'options'         => array('required' => true),
            'first_options'   => array('label' => 'Password'),
            'second_options'  => array('label' => 'Repeat Password'),
        ))
        ->getForm()
    ;

    if ($request->isMethod('POST')) {
        $form->bind($request);
        if ($form->isValid()) {
            $app['session']->getFlashBag()->add('success', 'The form is valid');
        } else {
            $form->addError(new FormError('This is a global error'));
            $app['session']->getFlashBag()->add('info', 'The form is bind, but not valid');
        }
    }

    return $app['twig']->render('form.html.twig', array('form' => $form->createView()));
})->bind('form');

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
