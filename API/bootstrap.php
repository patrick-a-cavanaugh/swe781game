<?php

use Knp\Provider\ConsoleServiceProvider;
use Game\GameApp;
use Symfony\Component\HttpFoundation\Request;
use Game\Util\BcryptPasswordEncoder;
use Game\Util\UserProvider;
use Symfony\Component\HttpFoundation\Response;
use Game\Util\SecurityUtils;
use Game\Form\UserRegistration;
use Game\Util\ControllerUtils;
use Game\Validator\UniqueDbFieldValidator;
use Game\Validator\CustomConstraintValidatorFactory;
use Game\GameAppConfig;
use Symfony\Component\HttpFoundation\Cookie;
use Game\Form\CreateGame;
use Game\Model\Game;
use Game\Model\Player;
use Game\Model\PlayerShipType;
use Game\Model\PlayerMove;
use Game\Model\CargoTransaction;

require_once __DIR__ . '/vendor/autoload.php';

global $app; // global so that the config files can access it.

$app = new GameApp();

/* Configure error reporting to include all errors and warnings, and throw an exception to prevent ignoring errors. */

error_reporting(E_ALL|E_STRICT);
/**
 * Based on <a href="http://php.net/manual/en/class.errorexception.php">PHP.net ErrorException handler</a>.
 *
 * @param $errorCode int the error code
 * @param $errorString string the error message
 * @param $errorFile string the file that raised the error
 * @param $errorLine int the line the error was thrown from
 * @throws ErrorException
 */
if (!defined('WEB_TEST_CASE')) {
    function exception_error_handler($errorCode, $errorString, $errorFile, $errorLine)
    {
        throw new ErrorException($errorString, $errorCode, 0, $errorFile, $errorLine);
    }

    set_error_handler("exception_error_handler");
}

if (!defined('CONFIG_BASE_DIR')) {
    // These are inside the guard because PHPUnit runs them more than once
    define('CONFIG_BASE_DIR', __DIR__.'/config');
}
$ENV = getenv('GAMEAPP_ENV');
if (empty($ENV)) {
    trigger_error('No GAMEAPP_ENV is set. You must set the GAMEAPP_ENV variable to either dev, test, or prod',
        E_USER_ERROR);
}
if (!defined('ENV')) {
    define('ENV', $ENV);
}

require CONFIG_BASE_DIR . "/global.config.php";
/** @noinspection PhpIncludeInspection - false positive */
require CONFIG_BASE_DIR . "/" . ENV . ".config.php";
require CONFIG_BASE_DIR . "/verify.config.php";

// service providers
$app->register(new ConsoleServiceProvider(), array(
    'console.name' => 'GameApp',
    'console.version' => '1.0.0',
    'console.project_directory' => __DIR__
));
// Swiftmailer options are set in the config files
$app->register(new Silex\Provider\SwiftmailerServiceProvider(), array(
    'swiftmailer.options' => GameAppConfig::$mailerOptions
));
if (GameAppConfig::$disableMailer) {
    $app["swiftmailer.transport"] =
        new \Swift_Transport_NullTransport($app['swiftmailer.transport.eventdispatcher']);
}
$app->register(new \Knp\Provider\MigrationServiceProvider(), array(
    'migration.path' => __DIR__ . '/src/resources/migrations'
));
$app->register(new Silex\Provider\DoctrineServiceProvider(), array(
    'db.options' => GameAppConfig::$dbOptions,
));
$app->register(new Silex\Provider\MonologServiceProvider(), array(
    'monolog.logfile' => __DIR__.'/logs/'.ENV.'.log',
));
$app->register(new Silex\Provider\ValidatorServiceProvider());
$app['validator.validator_factory'] = $app->share(function ($app) {
    return new CustomConstraintValidatorFactory($app);
});
$app->register(new Silex\Provider\UrlGeneratorServiceProvider());
$app->register(new Silex\Provider\FormServiceProvider());
$app->register(new Silex\Provider\SecurityServiceProvider());
$app->register(new Silex\Provider\SessionServiceProvider(), array(
    'session.storage.options' => array(
        'name' => '_GAMEAPP_SESS',
        'secure' => true,
        'httponly' => true
    )
));
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__ . '/src/twig',
));
$app['unique_db_field_validator'] = $app->share(function (GameApp $app) {
    return new UniqueDbFieldValidator($app['db']);
});

// Security definitions
$app['security.encoder.digest'] = $app->share(function () {
    return new BcryptPasswordEncoder();
});
$app['security.firewalls'] = array(
    'login' => array(
        'pattern' => '^(/login|/user/create|/user/[0-9]+/confirmEmail)$',
    ),
    'secured' => array(
        'pattern' => '^.*$',
        'form' => array(
            'with_csrf' => true,
            'login_path' => '/login',
            'check_path' => '/login_check',
            'csrf_provider' => 'form.csrf_provider',
            'intention' => 'app-wide',
            'always_use_default_target_path' => true,
            'default_target_path' => '/redirect_to_client'
        ),
        'logout' => array(
            'with_csrf'      => true,
            'logout_path'    => '/logout',
            'csrf_parameter' => '_csrf_token',
            'csrf_provider'  => 'form.csrf_provider',
            'intention'      => 'app-wide'
        ),
        'users' => $app->share(function () use ($app) {
            return new UserProvider($app['db']);
        }),
    ),
);

$app['security.access_rules'] = array(
    // Prevent access except through HTTPS
    array('^.*$', 'IS_AUTHENTICATED_ANONYMOUSLY', 'https'),
);

$app['PlayerService'] = $app->share(function (GameApp $app) {
    return new \Game\Service\PlayerService($app['db'], $app['monolog'], $app->user(), $app['GameService'],
        $app['UniverseService']);
});

$app['UniverseService'] = $app->share(function (GameApp $app) {
    return new \Game\Service\UniverseService($app['db'], $app['monolog'], $app->user());
});

$app['GameService'] = $app->share(function (GameApp $app) {
    return new \Game\Service\GameService($app['db'], $app['monolog'], $app->user());
});

$app['CargoService'] = $app->share(function (GameApp $app) {
    return new \Game\Service\CargoService($app['db'], $app['monolog'], $app->user(), $app['GameService'],
        $app['PlayerService'], $app['UniverseService']);
});

// route and controller definitions

/** @noinspection PhpUndefinedMethodInspection */
$app['controllers']
    ->before(function (Request $request, GameApp $app) {
        // All requests that may change state must include the CSRF token
        if ($request->getMethod() !== 'GET' &&  $request->getMethod() !== 'HEAD') {
            $appToken = $app->csrfToken('app-wide');

            if ( ! SecurityUtils::safeCompareStrings($appToken, $request->headers->get('x-gameapp-csrf-token'))) {
                $app->abort(403, 'Invalid or missing CSRF token the only headers were' . print_r($request->headers->all(), true) . " and we needed " . $appToken);
            }
        }

        // Some requests may be application/json and should be converted
        if (0 === strpos($request->headers->get('Content-Type'), 'application/json')) {
            $data = json_decode($request->getContent(), true);
            $request->request->replace(is_array($data) ? $data : array());
        }
    })
    ->after(function (Request $request, Response $response, GameApp $app) {
        $response->headers->set('x-gameapp-csrf-token', $app->csrfToken('app-wide'));
    });

if (defined('REQUIRED_SCHEME')) {
    /** @noinspection PhpUndefinedMethodInspection */
    $app['controllers']->setRequirement('_scheme', REQUIRED_SCHEME);
}

$app->get("/", function (GameApp $app) {
    return $app->json([
        'currentUser' => $app->user()->getUsername(),
        'userId' => $app->user()->getId(),
        'csrfToken' => $app->csrfToken('app-wide')
    ]);
});

$app->get("/redirect_to_client", function (GameApp $app) {
    return $app->redirect(GameAppConfig::$clientBaseUrl);
});

$app->get("/login", function (GameApp $app, Request $request) {
    return $app->render('login.html', array(
        'error' => $app['security.last_error']($request),
        'last_username' => $app->session()->get('_security.last_username'),
        'csrf_token' => $app->csrfProvider()->generateCsrfToken('app-wide')
    ));
});

$app->post("/login_check", function (GameApp $app) {
    // We don't need to set anything here as the Symfony Security firewall takes care of this request.
});

$app->get("/user/{userId}/confirmEmail", function (Request $request, GameApp $app) {
    $user = $app->db()->fetchAssoc("SELECT id, username FROM `user` WHERE `email_confirm_token` = ?",
        [$request->get('token')]);

    if ($user === false) {
        $app->abort(400, 'Unable to find or update the provided email confirmation token.');
    }
    $app->db()->update("user", ['email_confirmed' => true], ['id' => $user['id']]);
    $response = $app->redirect(GameAppConfig::$clientBaseUrl);
    $response->headers->setCookie(new Cookie("flash",
        "Your email address has been confirmed. Please login to your user account \"${user['username']}\" now.",
         0, '/', null, false, false));
    return $response;
})->bind('userEmailConfirmation')->assert('userId', '\d+');;

$app->post("/user/create", function (Request $request, GameApp $app) {
    $form = new UserRegistration();
    $form->emailAddress = $request->get('emailAddress');
    $form->userName = $request->get('userName');
    $form->password = $request->get('password');
    $form->passwordConfirmation = $request->get('passwordConfirmation');

    $errors = $app->validator()->validate($form);

    if (count($errors)) {
        return $app->json(ControllerUtils::transformErrorsToArray($errors), 400);
    } else {
        $app->db()->beginTransaction();
        try {
            $passwordEncoder = new BcryptPasswordEncoder();

            $emailConfirmToken = bin2hex(openssl_random_pseudo_bytes(16));
            $app->db()->insert('user', [
                'email_address' => $form->emailAddress,
                'password_hash' => $passwordEncoder->encodePassword($form->password, null),
                'username' => $form->userName,
                'date_registered' => date('Y-m-d H:i:s'),
                'email_confirm_token' => $emailConfirmToken
            ], [
                PDO::PARAM_STR, PDO::PARAM_STR, PDO::PARAM_STR, PDO::PARAM_STR
            ]);
            $userId = $app->db()->lastInsertId();

            // SUCCESS. We now send the confirmation email and return a HTTP 200 OK response to let the client
            // know it worked.
            $message = Swift_Message::newInstance('Please confirm your email address for SecurityGameProject',
                'You have registered a new user account on SecurityGameProject\'s web site. Thanks!' . "\n\n" .
                'The next step is for you to validate that this email address, "' . $form->emailAddress .
                '", belongs to you. Please click the link below, or copy and paste it into your web browser, ' .
                'to validate your email and join the fun!' . "\n\n" .
                $app->url('userEmailConfirmation', ['userId' => $userId, 'token' => $emailConfirmToken]),
                'text/plain',
                'UTF-8'
            );
            $message->setTo(GameAppConfig::$redirectMails ? 'me@patcavanaugh.info' : $form->emailAddress);
            $message->setFrom('sgp@patcavanaugh.info');
            $app->mail($message);
            $app->db()->commit();
            return $app->json(['username' => $form->userName]);
        } catch (Exception $exception) {
            if ($app->db()->isTransactionActive()) {
                $app->db()->rollback();
            }
            throw $exception;
        }
    }
});

$app->get("/players/{playerId}", function (GameApp $app, $playerId) {
    /** @var $playerService \Game\Service\PlayerService */
    $playerService = $app['PlayerService'];

    $player = $playerService->getPlayerById($playerId, true);
    if (!isset($player['user_id']) || $player['user_id'] != $app->user()->getId()) {
        $app->abort(404, 'Not Found');
    }
    return $app->json([
        'player' => $player
    ]);
});

$app->get("/games/{gameId}/map", function (Request $request, GameApp $app, $gameId) {
    // For now all games have the same map and there is no dynamic display (e.g. of player location)
    // as a result there is no need to validate gameId is a valid gameId.
    return $app->stream(function () {
        readfile(__DIR__ . '/src/resources/images/sgp_map400x300.png');
    }, 200, ['Content-Type' => 'image/png']);
})->assert('gameId', '\d+');;

$app->get("/games", function (GameApp $app) {
    // All players can see the basic info about all game lobbies. Nothing secret here so no authorization needed.

    /** @var $gameService \Game\Service\GameService */
    $gameService = $app['GameService'];
    $gameList = $gameService->fetchAllGames();

    return $app->json(['gameList' => $gameList]);
});

$app->get("/games/{gameId}", function (GameApp $app, $gameId) {
    /** @var $gameService \Game\Service\GameService */
    $gameService = $app['GameService'];
    $theGame = $gameService->fetchGameById($gameId);

    // Basic information about every game is available to all players so there is no security requirement here.
    return $app->json(['game' => $theGame]);
});

// UPDATE for a game is joining or starting it.
$app->put("/games/{gameId}", function (Request $request, GameApp $app, $gameId) {
    /** @var $gameService \Game\Service\GameService */
    $gameService = $app['GameService'];
    $currentGame = $gameService->fetchGameById($gameId);

    /** @var $playerService \Game\Service\PlayerService */
    $playerService = $app['PlayerService'];

    // Join a game
    if (!$currentGame['joined'] && $request->get('joined')) {
        $playerService->addPlayerToGame($currentGame);
    }

    // Start a game
    if ($currentGame['status'] === Game::Status_Waiting && $request->get('status') === Game::Status_InProgress) {
        if ($currentGame['players'] >= 2) {
            if ($currentGame['joined']) {
                if ($currentGame['created_by_id'] == $app->user()->getId()) {
                    $gameService->startGame($currentGame);
                } else {
                    trigger_error('Only the creator of the game may start it.', E_USER_ERROR);
                }
            } else {
                trigger_error('Cannot start a game you haven\'t joined.', E_USER_ERROR);
            }
        } else {
            trigger_error('Cannot start a game with less than two players.', E_USER_ERROR);
        }
    }

    return $app->json(['game' => $gameService->fetchGameById($gameId)]);
})->assert('gameId', '\d+');;

$app->post("/games", function (Request $request, GameApp $app) {
    $createGameForm = new CreateGame();
    $createGameForm->name = $request->get('name');

    $errors = $app->validator()->validate($createGameForm);

    if (count($errors)) {
        return $app->json(ControllerUtils::transformErrorsToArray($errors), 400);
    } else {
        $app->db()->beginTransaction();
        try {
            $app->db()->insert('game', [
                'date_created' => date('Y-m-d H:i:s'),
                'status' => Game::Status_Waiting,
                'name' => $createGameForm->name,
                'created_by_id' => $app->user()->getId()
            ]);
            $game = $app->db()->fetchAssoc(<<<SQL
SELECT id, date_created, status, name, date_started, created_by_id FROM game
WHERE id = ?
SQL
                , [$app->db()->lastInsertId()]);

            /** @var $universeService \Game\Service\UniverseService */
            $universeService = $app['UniverseService'];
            /** @var $playerService \Game\Service\PlayerService */
            $playerService = $app['PlayerService'];
            /** @var $gameService \Game\Service\GameService */
            $gameService = $app['GameService'];


            $universeService->createUniverseForGame($game['id']);

            $player = $playerService->addPlayerToGame($game);

            // Now that the player and universe are added, refresh the game to a full game state.
            $game = $gameService->fetchGameById($game['id']);

            $app->db()->commit();
        } catch (Exception $e) {
            $app->db()->rollback();
            throw $e;
        }
        return $app->json([
            'game' => Game::setTypes($game),
            'player' => $player
        ]);
    }
});

$app->get("/planets", function (GameApp $app) {
    /** @var $universeService \Game\Service\UniverseService */
    $universeService = $app['UniverseService'];

    return $app->json([
        // Returns all planets from all active games... not incredibly scalable.
        'planetList' => $universeService->fetchAllPlanets()
    ]);
});

$app->get("/planets/{planetId}", function (GameApp $app, $planetId) {
    /** @var $universeService \Game\Service\UniverseService */
    $universeService = $app['UniverseService'];
    $planet = $universeService->fetchPlanetById($planetId);

    if (!$planet) {
        $app->abort(404, 'Not found');
    }
    return $app->json(['planet' => $planet]);
})->assert('planetId', '\d+');;

$app->get('/planets/{planetId}/cargo', function (GameApp $app, $planetId) {
    try {
        /** @var $cargoService \Game\Service\CargoService */
        $cargoService = $app['CargoService'];
        $cargoList = $cargoService->getCargoForPlanet($planetId);
        return $app->json(['cargoList' => $cargoList]);
    } catch (\Exception $e) {
        $app->log('While attempting to retrieve cargo, got an exception.', ['message' => $e->getMessage(),
            'stackTrace' => $e->getTraceAsString()]);
        $app->abort(404, 'Not Found');
        return null;
    }
})->assert('planetId', '\d+');;

$app->get("/players/{playerId}/moves", function (GameApp $app, $playerId) {
    /** @var $playerService \Game\Service\PlayerService */
    $playerService = $app['PlayerService'];

    return $app->json([
        'playerMoveList' => $playerService->fetchAllPlayerMoves($playerId)
    ]);
})->assert('playerId', '\d+');

$app->get("/playerMoves/{playerMoveId}", function (GameApp $app, $playerMoveId) {
    /** @var $playerService \Game\Service\PlayerService */
    $playerService = $app['PlayerService'];

    return $app->json([
        'playerMove' => $playerService->fetchPlayerMoveById($playerMoveId)
    ]);
})->assert('playerMoveId', '\d+');

$app->post("/playerMoves/{playerId}/moves", function (Request $request, GameApp $app, $playerId) {
    /** @var $playerService \Game\Service\PlayerService */
    $playerService = $app['PlayerService'];
    /** @var $universeService \Game\Service\UniverseService */
    $universeService = $app['UniverseService'];
    /** @var $cargoService \Game\Service\CargoService */
    $cargoService = $app['CargoService'];

    $proposedMove = new PlayerMove([
        'playerId' => intval($playerId),
        'gameTurnNo' => intval($request->get('gameTurnNo')),
        'moveNo' => intval($request->get('moveNo')),
        'type' => $request->get('type'),
        'playerMoveDestinationId' => intval($request->get('playerMoveDestinationId'))
    ]);

    $errors = $app->validator()->validate($proposedMove);

    if (count($errors)) {
        return $app->json(ControllerUtils::transformErrorsToArray($errors), 400);
    } else {
        $moveId = $playerService->checkAndMakeMove($proposedMove);
        $player = $playerService->getPlayerById($proposedMove->playerId);
        $playerMove = $playerService->fetchPlayerMoveById($moveId);
        $currentLocation = $universeService->fetchPlanetById($playerMove['player_move_destination_id']);
        $currentLocation['cargo'] = $cargoService->getCargoForPlanet($currentLocation['id']);
        return $app->json([
            'player' => $player,
            'playerMove' => $playerMove,
            'currentLocation' => $currentLocation
        ]);
    }
})->assert('playerId', '\d+');;

$app->post("/playerCargoTransactions", function (Request $request, GameApp $app) {
    $proposedTransaction = new CargoTransaction($request->get('cargo_type_id'),
        $request->get('player_id'), $request->get('size'), $request->get('type'));

    $errors = $app->validator()->validate($proposedTransaction);

    if (count($errors)) {
        return $app->json(ControllerUtils::transformErrorsToArray($errors), 400);
    } else {
        try {
            /** @var $cargoService \Game\Service\CargoService */
            $cargoService = $app['CargoService'];
            /** @var $playerService \Game\Service\PlayerService */
            $playerService = $app['PlayerService'];

            $cargoService->executeTransaction($proposedTransaction);

            return $app->json([
                'player' => $playerService->getPlayerById($proposedTransaction->playerId)
            ]);
        } catch (\Game\Service\CargoServiceException $e) {
            return $app->json(ControllerUtils::transformErrorsToArray($e->errors), 400);
        }
    }
});

$app->get('/statistics', function (GameApp $app) {
    //- see the win/loss statistics of themselves and others
    //- see game moves of completed games,

    $statistics = [];

    $statistics['playerRankings'] = $app['PlayerService']->getPlayerRankings();

    $allGames = $app['GameService']->fetchAllGames();
    $statistics['gameMoves'] = [];
    foreach ($allGames as $game) {
        if ($game['status'] === Game::Status_Completed) {
            $statistics['gameMoves'][$game['id']]
                = $app['PlayerService']->fetchAllMovesForGame($game['id']);
        }
    }

    return $app->json($statistics);
});

return $app;