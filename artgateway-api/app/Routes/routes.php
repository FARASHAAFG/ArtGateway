<?php

declare(strict_types=1);

use App\Controllers\AboutController;
use App\Controllers\ArtworkController;
use App\Controllers\ArtistController;
use App\Controllers\MuseumController;
use App\Helpers\DateTimeHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Controllers\ArtworkExternalController;
use App\Controllers\ArtistExternalController;


return static function (Slim\App $app): void {

    // Routes without authentication check: /login, /token

    //* ROUTE: GET /
    $app->get('/', [AboutController::class, 'handleAboutWebService']);

    // ARTWORKS
    $app->get('/artworks', [ArtworkController::class, 'handleGetArtworks']);
    $app->get('/artworks/{id}', [ArtworkController::class, 'handleGetArtworkById']);
    $app->post('/artworks', [ArtworkController::class, 'handleCreateArtwork']);
    $app->put('/artworks/{id}', [ArtworkController::class, 'handleUpdateArtwork']);
    $app->delete('/artworks/{id}', [ArtworkController::class, 'handleDeleteArtwork']);

    // Composite resource: our artwork + info from the Art Institute of Chicago
    $app->get('/artworks/{id}/external', [ArtworkExternalController::class, 'handleGetArtworkExternal']);
    // Composite resource: our artist + our artworks + info from the Art Institute of Chicago
       $app->get('/artists/{id}/external', [ArtistExternalController::class, 'handleGetArtistExternal']);

    // ARTISTS
    $app->get('/artists', [ArtistController::class, 'handleGetArtists']);
    $app->get('/artists/{id}', [ArtistController::class, 'handleGetArtistById']);
    $app->get('/artists/{id}/artworks', [ArtistController::class, 'handleGetArtworksByArtist']);
    $app->post('/artists', [ArtistController::class, 'handleCreateArtist']);
    $app->put('/artists/{id}', [ArtistController::class, 'handleUpdateArtist']);
    $app->delete('/artists/{id}', [ArtistController::class, 'handleDeleteArtist']);

    // MUSEUMS
    $app->get('/museums', [MuseumController::class, 'handleGetMuseums']);
    $app->get('/museums/{id}', [MuseumController::class, 'handleGetMuseumById']);
    $app->get('/museums/{id}/artworks', [MuseumController::class, 'handleGetArtworksByMuseum']);

    //* NOTE: callback naming pattern: handle<ActionName>, e.g. handleGetPlayers
    //* ROUTE: GET /players
    //$app->get('/players', [PlayersController::class, 'handleGetPlayers']);

    //* ROUTE: GET /ping
    $app->get('/ping', function (Request $request, Response $response, $args) {

        $payload = [
            "greetings" => "Reporting! Hello there!",
            "now" => DateTimeHelper::now(DateTimeHelper::Y_M_D_H_M),
        ];
        $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR));
        return $response;
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
