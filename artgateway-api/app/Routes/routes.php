<?php

declare(strict_types=1);

use App\Controllers\AboutController;
use App\Controllers\ArtworkController;
use App\Controllers\ArtistController;
use App\Controllers\MuseumController;
use App\Controllers\AuthController;
use App\Domain\Models\UserModel;
use App\Helpers\DateTimeHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use App\Controllers\ArtworkExternalController;
use App\Controllers\ArtistExternalController;
use App\Middleware\AuthMiddleware;
use App\Services\JwtService;


return static function (Slim\App $app): void {

    $container = $app->getContainer();
    $userAuth = function (Request $request, RequestHandler $handler) use ($container): Response {
        return (new AuthMiddleware(
            $container->get(JwtService::class),
            static fn (): UserModel => $container->get(UserModel::class)
        ))($request, $handler);
    };
    $adminAuth = function (Request $request, RequestHandler $handler) use ($container): Response {
        return (new AuthMiddleware(
            $container->get(JwtService::class),
            static fn (): UserModel => $container->get(UserModel::class),
            'admin'
        ))($request, $handler);
    };

    // Authentication
    $app->post('/register', [AuthController::class, 'handleRegister']);
    $app->post('/login', [AuthController::class, 'handleGenerateToken']);
    $app->post('/token', [AuthController::class, 'handleGenerateToken']);
    $app->get('/me', [AuthController::class, 'handleGetCurrentUser'])->add($userAuth);

    //* ROUTE: GET /
    $app->get('/', [AboutController::class, 'handleAboutWebService']);

    // ARTWORKS
    $app->get('/artworks', [ArtworkController::class, 'handleGetArtworks']);
    $app->get('/artworks/{id}', [ArtworkController::class, 'handleGetArtworkById']);
    $app->post('/artworks', [ArtworkController::class, 'handleCreateArtwork'])->add($adminAuth);
    $app->put('/artworks/{id}', [ArtworkController::class, 'handleUpdateArtwork'])->add($adminAuth);
    $app->delete('/artworks/{id}', [ArtworkController::class, 'handleDeleteArtwork'])->add($adminAuth);

    // Composite resource: our artwork + info from the Art Institute of Chicago
    $app->get('/artworks/{id}/external', [ArtworkExternalController::class, 'handleGetArtworkExternal']);
    // Composite resource: our artist + our artworks + info from the Art Institute of Chicago
       $app->get('/artists/{id}/external', [ArtistExternalController::class, 'handleGetArtistExternal']);

    // ARTISTS
    $app->get('/artists', [ArtistController::class, 'handleGetArtists']);
    $app->get('/artists/{id}', [ArtistController::class, 'handleGetArtistById']);
    $app->get('/artists/{id}/artworks', [ArtistController::class, 'handleGetArtworksByArtist']);
    $app->post('/artists', [ArtistController::class, 'handleCreateArtist'])->add($adminAuth);
    $app->put('/artists/{id}', [ArtistController::class, 'handleUpdateArtist'])->add($adminAuth);
    $app->delete('/artists/{id}', [ArtistController::class, 'handleDeleteArtist'])->add($adminAuth);

    // MUSEUMS
    $app->get('/museums', [MuseumController::class, 'handleGetMuseums']);
    $app->get('/museums/{id}', [MuseumController::class, 'handleGetMuseumById']);
    $app->get('/museums/{id}/artworks', [MuseumController::class, 'handleGetArtworksByMuseum']);
    $app->post('/museums', [MuseumController::class, 'handleCreateMuseum'])->add($adminAuth);
    $app->put('/museums/{id}', [MuseumController::class, 'handleUpdateMuseum'])->add($adminAuth);
    $app->delete('/museums/{id}', [MuseumController::class, 'handleDeleteMuseum'])->add($adminAuth);

    //* NOTE: callback naming pattern: handle<ActionName>, e.g. handleGetPlayers
    //* ROUTE: GET /players
    //$app->get('/players', [PlayersController::class, 'handleGetPlayers']);

    //* ROUTE: GET /ping
    $app->get('/ping', function (Request $request, Response $response, $args) {

        $payload = [
            "greetings" => "Reporting! Hello there!",
            "now" => DateTimeHelper::now(DateTimeHelper::Y_M_D_H_M),
        ];
        $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        return $response->withHeader('Content-Type', APP_MEDIA_TYPE_JSON);
    });

    // Example route to test error handling.
    $app->get('/error', function (Request $request, Response $response, $args) {
        throw new \Slim\Exception\HttpNotFoundException($request, "Something went wrong");
    });

    //* ROUTE: GET /phpinfo -> Display PHP configuration (useful for Docker)
    //* Docker URL: http://localhost:8080/phpinfo
    $app->get('/phpinfo', function (Request $request, Response $response, $args) {
        ob_start();
        phpinfo();
        $info = ob_get_clean();
        $response->getBody()->write($info);
        return $response->withHeader('Content-Type', 'text/html');
    });


};
