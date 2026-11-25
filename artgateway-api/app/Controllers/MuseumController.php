<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\Models\MuseumModel;
use App\Exceptions\ApiException;
use App\Helpers\RequestValidator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class MuseumController extends BaseController
{
    public function __construct(private MuseumModel $museumModel)
    {
    }

    public function handleGetMuseums(Request $request, Response $response): Response
    {
        $validator = new RequestValidator($request->getQueryParams());
        $validator->integer('page', false, 1, 1000);
        $validator->integer('limit', false, 1, 100);

        if ($validator->hasErrors()) {
            throw new ApiException(400, 'Validation failed', $validator->getErrors());
        }

        $validated = $validator->getValidated();
        $page = $validated['page'] ?? 1;
        $limit = $validated['limit'] ?? 20;
        $result = $this->buildPaginatedResult(
            $request,
            $this->museumModel->getAll($limit, ($page - 1) * $limit),
            $page,
            $limit,
            $this->museumModel->countAll()
        );

        return $this->renderResponse($request, $response, $result);
    }

    public function handleGetMuseumById(Request $request, Response $response, array $args): Response
    {
        $id = $this->positivePathId($args);
        $museum = $this->museumModel->getById($id);

        if (!$museum) {
            throw new ApiException(404, 'Museum not found');
        }

        return $this->renderResponse($request, $response, $museum);
    }

    public function handleGetArtworksByMuseum(Request $request, Response $response, array $args): Response
    {
        $id = $this->positivePathId($args);
        $validator = new RequestValidator($request->getQueryParams());
        $validator->integer('page', false, 1, 1000);
        $validator->integer('limit', false, 1, 100);

        if ($validator->hasErrors()) {
            throw new ApiException(400, 'Validation failed', $validator->getErrors());
        }

        $validated = $validator->getValidated();
        if (!$this->museumModel->getById($id)) {
            throw new ApiException(404, 'Museum not found');
        }

        $page = $validated['page'] ?? 1;
        $limit = $validated['limit'] ?? 20;
        $result = $this->buildPaginatedResult(
            $request,
            $this->museumModel->getArtworksByMuseumId($id, $limit, ($page - 1) * $limit),
            $page,
            $limit,
            $this->museumModel->countArtworksByMuseumId($id)
        );

        return $this->renderResponse($request, $response, $result);
    }

    public function handleCreateMuseum(Request $request, Response $response): Response
    {
        $data = $this->validateMuseum($this->getRequestBody($request));
        $id = $this->museumModel->create($data);
        $museum = $this->museumModel->getById($id);
        $location = rtrim($request->getUri()->getPath(), '/') . '/' . $id;

        return $this->renderResponse($request, $response, $museum, 201)
            ->withHeader('Location', $location);
    }

    public function handleUpdateMuseum(Request $request, Response $response, array $args): Response
    {
        $id = $this->positivePathId($args);
        if (!$this->museumModel->getById($id)) {
            throw new ApiException(404, 'Museum not found');
        }

        $data = $this->validateMuseum($this->getRequestBody($request));
        $this->museumModel->updateById($id, $data);

        return $this->renderResponse($request, $response, $this->museumModel->getById($id));
    }

    public function handleDeleteMuseum(Request $request, Response $response, array $args): Response
    {
        $id = $this->positivePathId($args);
        if (!$this->museumModel->getById($id)) {
            throw new ApiException(404, 'Museum not found');
        }

        $this->museumModel->deleteById($id);
        return $response->withStatus(204);
    }

    private function validateMuseum(array $body): array
    {
        $errors = [];
        $name = $this->checkString($body, 'name', true, 200, $errors);
        $city = $this->checkString($body, 'city', true, 100, $errors);
        $country = $this->checkString($body, 'country', true, 100, $errors);
        $website = $this->checkString($body, 'website', false, 2048, $errors);

        $this->throwIfInvalid($errors);

        return [
            'name' => $name,
            'city' => $city,
            'country' => $country,
            'website' => $website,
        ];
    }
}
