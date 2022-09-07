<?php

use DI\Container;
use Laminas\Filter\Digits;
use Laminas\Filter\StringTrim;
use Laminas\Filter\StripTags;
use Laminas\InputFilter\Input;
use Laminas\InputFilter\InputFilter;
use Laminas\Validator\NotEmpty;
use Laminas\Validator\Regex;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;

require __DIR__ . '/../vendor/autoload.php';

$container = new Container;

$container->set(InputFilter::class, function(): InputFilter {
    $image = new Input('image');
    $image->getValidatorChain()
        ->attach(new NotEmpty());
    $image->getFilterChain()
        ->attach(new StringTrim())
        ->attach(new StripTags())
        ->attach(new Digits());

    $message = new Input('message');
    $message->getValidatorChain()
        ->attach(new NotEmpty());
    $message->getFilterChain()
        ->attach(new StringTrim())
        ->attach(new StripTags());

    $phoneNumber = new Input('phone_number');
    $phoneNumber->getValidatorChain()
        ->attach(new Regex([
            'pattern' => '/^\+[1-9]\d{1,14}$/'
        ]));
    $phoneNumber->getFilterChain()
        ->attach(new StringTrim())
        ->attach(new StripTags());

    $inputFilter = new InputFilter();
    $inputFilter->add($image);
    $inputFilter->add($message);
    $inputFilter->add($phoneNumber);

    return $inputFilter;
});

// The image to choose from
$container->set('images', function (): array {
    return [
        'Emoji Vampire' => '/images/emoji-vampire.png',
        'Ghost' => '/images/ghost.png',
        'Halloween Jack O\'lantern' => '/images/halloween_jack-olantern.png',
        'Halloween Scene' => '/images/halloween-scene.png',
        'Happy Halloween 2' => '/images/happy-halloween-2.png',
        'Happy Halloween' => '/images/happy-halloween.png',
        'Jack O\'Lantern' => '/images/jack-olantern.png',
        'Jack O\'Lantern (PV)' => '/images/jack-o-lantern-pv.png',
        'Kid Vampire' => '/images/kid-vampire.png',
        'A Spooky Jack O\'Lantern' => '/images/spooky-jack-olantern.png',
        'Trick or Treat' => '/images/trick-or-treat.png',
        'The Vampire' => '/images/vampire.png',
    ];
});

AppFactory::setContainer($container);
$app = AppFactory::create();

$app->add(TwigMiddleware::create(
    $app,
    Twig::create(
        __DIR__ . '/../src/templates/',
        ['cache' => false]
    )
));

$app->map(['GET','POST'], '/', function (Request $request, Response $response, array $args) {
    $data = [];

    if ($request->getMethod() === 'POST') {
        /** @var InputFilter $inputFilter */
        $inputFilter = $this->get(InputFilter::class);
        $inputFilter->setData((array)$request->getParsedBody());
        if (! $inputFilter->isValid()) {
            $data['errors'] = $inputFilter->getMessages();
            $data['values'] = $inputFilter->getValues();
        }
    }

    $view = Twig::fromRequest($request);
    return $view->render($response, 'default.html.twig', $data);
});

$app->run();