<?php

use DI\Container;
use Laminas\Filter\StringTrim;
use Laminas\Filter\StripTags;
use Laminas\InputFilter\Input;
use Laminas\InputFilter\InputFilter;
use Laminas\Validator\NotEmpty;
use Laminas\Validator\Regex;
use Laminas\Validator\StringLength;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;
use Twilio\Rest\Client;

require __DIR__ . '/../vendor/autoload.php';

/**
 * Load environment variables from .env in the project's
 * top-level directory
 */
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();
$dotenv->required([
    'TWILIO_ACCOUNT_SID',
    'TWILIO_AUTH_TOKEN',
    'TWILIO_PHONE_NUMBER',
]);

/**
 * Instantiate a DI container
 */
$container = new Container;

/**
 * An InputFilter for filtering and validating the form information
 */
$container->set(InputFilter::class, function(): InputFilter {
    $image = new Input('image');
    $image->getValidatorChain()
        ->attach(new NotEmpty());
    $image->getFilterChain()
        ->attach(new StringTrim())
        ->attach(new StripTags());

    $message = new Input('message');
    $message->getValidatorChain()
        ->attach(new NotEmpty())
        ->attach(new StringLength(['max' => 320]));
    $message->getFilterChain()
        ->attach(new StringTrim())
        ->attach(new StripTags());

    $phoneNumber = new Input('phone_number');
    $phoneNumber->getValidatorChain()
        ->attach(new Regex(['pattern' => '/^\+[1-9]\d{1,14}$/']));
    $phoneNumber->getFilterChain()
        ->attach(new StringTrim())
        ->attach(new StripTags());

    $inputFilter = new InputFilter();
    $inputFilter->add($image);
    $inputFilter->add($message);
    $inputFilter->add($phoneNumber);

    return $inputFilter;
});

/**
 * The Twilio Client object for interacting with the Programmable SMS API.
 *
 * @see https://www.twilio.com/docs/sms/tutorials/how-to-send-sms-messages-php
 */
$container->set(Client::class, function (): Client {
    return new Client(
        $_SERVER["TWILIO_ACCOUNT_SID"],
        $_SERVER["TWILIO_AUTH_TOKEN"]
    );
});

/**
 * The images that the user can choose from
 */
$container->set('images', function (): array {
    return [
        [
            'name' => 'Emoji Vampire',
            'label' => 'emoji-vampire',
            'image' => $_SERVER["IMAGE_URL_BASE"] . '/emoji-vampire.png'
        ],
        [
            'name' => 'Ghost',
            'label' => 'ghost',
            'image' => $_SERVER["IMAGE_URL_BASE"] . '/ghost.png'
        ],
        [
            'name' => 'Halloween Jack O\'lantern',
            'label' => 'halloween-jack-olantern',
            'image' => $_SERVER["IMAGE_URL_BASE"] . '/halloween_jack-olantern.png'
        ],
        [
            'name' => 'Halloween Scene',
            'label' => 'halloween-scene',
            'image' => $_SERVER["IMAGE_URL_BASE"] . '/halloween-scene.png'
        ],
        [
            'name' => 'Happy Halloween 2',
            'label' => 'happy-halloween-2',
            'image' => $_SERVER["IMAGE_URL_BASE"] . '/happy-halloween-2.png'
        ],
        [
            'name' => 'Happy Halloween',
            'label' => 'happy-halloween',
            'image' => $_SERVER["IMAGE_URL_BASE"] . '/happy-halloween.png'
        ],
        [
            'name' => 'Jack O\'Lantern',
            'label' => 'jack-olantern',
            'image' => $_SERVER["IMAGE_URL_BASE"] . '/jack-olantern.png'
        ],
        [
            'name' => 'Jack O\'Lantern (PV)',
            'label' => 'jack-olantern-pv',
            'image' => $_SERVER["IMAGE_URL_BASE"] . '/jack-o-lantern-pv.png'
        ],
        [
            'name' => 'Kid Vampire',
            'label' => 'kid-vampire',
            'image' => $_SERVER["IMAGE_URL_BASE"] . '/kid-vampire.png'
        ],
        [
            'name' => 'A Spooky Jack O\'Lantern',
            'label' => 'spooky-jack-olantern',
            'image' => $_SERVER["IMAGE_URL_BASE"] . '/spooky-jack-olantern.png'
        ],
        [
            'name' => 'Trick or Treat',
            'label' => 'trick-or-treat',
            'image' => $_SERVER["IMAGE_URL_BASE"] . '/trick-or-treat.png'
        ],
        [
            'name' => 'The Vampire',
            'label' => 'the-vampire',
            'image' => $_SERVER["IMAGE_URL_BASE"] . '/vampire.png'
        ],
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

/**
 * The "thank you" route, where the user is redirected to after they've submitted the form
 *
 * @todo check that the route is only accessed after a form submission
 */
$app->map(['GET'], '/thank-you',
    function (Request $request, Response $response, array $args)
    {
        $view = Twig::fromRequest($request);
        return $view->render($response, 'thank-you.html.twig', []);
    }
);

/**
 * The default route
 */
$app->map(['GET','POST'], '/',
    function (Request $request, Response $response, array $args)
    {
        $data = [];

        if ($request->getMethod() === 'POST') {
            /** @var InputFilter $inputFilter */
            $inputFilter = $this->get(InputFilter::class);
            $inputFilter->setData((array)$request->getParsedBody());
            if (! $inputFilter->isValid()) {
                $data['errors'] = $inputFilter->getMessages();
                $data['values'] = $inputFilter->getValues();
            } else {
                $twilio = $this->get(Client::class);
                $twilio->messages
                    ->create($inputFilter->getValue('phone_number'),
                        [
                            "body" => $inputFilter->getValue('message'),
                            "from" => $_SERVER['TWILIO_PHONE_NUMBER'],
                            "mediaUrl" => [
                                sprintf(
                                    '%s/%s.png',
                                    $_SERVER["IMAGE_URL_BASE"],
                                    $inputFilter->getValue('image')
                                )
                            ]
                        ]
                    );

                return $response
                    ->withHeader('Location', '/')
                    ->withStatus(302);
            }
        }

        $data['images'] = $this->get('images');
        $view = Twig::fromRequest($request);

        return $view->render($response, 'default.html.twig', $data);
    }
);

$app->run();