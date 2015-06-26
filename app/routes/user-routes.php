<?php

    use Sarcoma\Users\Users;
    use Sarcoma\Email\Email;

    $app->get('/register/', function () use ($app) {
        $app->render('register.twig', array(
            'csrf' => functions\CSRF::generate()
        ));
    })->name('register');

    $app->get('/activate/:token/:hash/', function ($token, $hash) use ($app, $entityManager) {
        $user = $entityManager->getRepository('Sarcoma\Users\Users')->findOneBy(array('activation_token' => $token));
        if ($user) {
            if ($hash == $user->hashUsername($user->getUsername())) {
                $user->setActivationToken(true);
                $entityManager->persist($user);
                $entityManager->flush();
                $app->flash('message', 'User activated');
                $app->redirect($app->urlFor('message'));
            }
        }
        $app->flash('message', 'Could not activate account');
        $app->redirect($app->urlFor('message'));
    })->name('activate');

    $app->post('/create-user/', function () use ($app, $view, $entityManager) {

        $username = trim($_POST['username']);
        $email    = trim($_POST['email']);
        $password = trim($_POST['password']);
        if (functions\CSRF::check($_POST['csrf'])) {
            $user  = new Users();
            $error = $user->validate($username, $email, $password, $entityManager);
            if (empty($error)) {

                $user->persistUser($username, $email, $password);
                $user->setActivationToken();
                $entityManager->persist($user);
                $entityManager->flush();

                $createUser = ($user->getId() ? true : false);

                if ($createUser) {
                    // todo: Set Url in slim view.
                    $link    = 'http://localhost' . $app->urlFor('activate', array(
                            'token' => $user->getActivationToken(),
                            'hash'  => $user->hashUsername($user->getUsername())
                        ));
                    $message = new Email('#262729', 18);
                    $message->setBodyColor('#eeeeee');
                    $message->setTableColor('#e8e8e8');
                    $message->setEmailTitle('Account Activation');
                    $message->setTag('Welcome to the site', 'h1', array(
                        'font-size' => $message->modularScale(1),
                        'color'     => '#3fd5ae'
                    ));
                    $message->setTag('Please follow the link below to activate your account.', 'p');
                    $message->setLink('Activate Account', $link, 'p', array(
                        'color' => '#3fd5ae'
                    ));

                    $mail = new PHPMailer();
                    $mail->IsSMTP(); // enable SMTP
                    $mail->SMTPDebug  = 1; // debugging: 1 = errors and messages, 2 = messages only
                    $mail->SMTPAuth   = true; // authentication enabled
                    $mail->SMTPSecure = 'ssl'; // secure transfer enabled REQUIRED for GMail
                    $mail->Host       = "smtp.gmail.com";
                    $mail->Port       = 465; // or 587
                    $mail->IsHTML(true);
                    $mail->Username = "sncoopr@gmail.com";
                    $mail->Password = "cesspit2";
                    $mail->FromName = 'Slim';
                    $mail->From     = 'test@test.com';
                    $mail->AddAddress($email, $username);
                    $mail->Subject = 'Account Activation';

                    $mail->Body = $message->getTwigTemplate(array(
                            'message' => $message->getMessage(),
                            'footer'  => $message->makeTag('&copy; three&amp;me ltd 2015', 'p', array(
                                'font-size' => $message->modularScale(- 1)
                            ))
                        ), 'email/email.twig', __DIR__ . '/../../templates');

                    if ($mail->Send() || true) {
                        $app->flash('message', 'User created successfully');
                        $app->redirect($app->urlFor('message'));
                    } else {
                        $app->flash('message', 'There was an error sending activation details');
                        $app->redirect($app->urlFor('message'));
                    }
                } else {
                    $app->flash('message', 'User could not be created');
                    $app->redirect($app->urlFor('message'));
                }
            } else {
                $error['message'] = 'User could not be created, please check fields';
                $app->flash('error', $error);
                $app->redirect($app->urlFor('register'));
            }
        }
        $app->render('register.twig', array(
            'csrf' => functions\CSRF::generate()
        ));
    });