<?php

namespace App\Controllers\Api;

use App\Services\AmbassadorService;

class AmbassadorController extends BaseApiController
{
    private AmbassadorService $service;

    public function __construct()
    {
        $this->service = new AmbassadorService();
    }

    public function index(): \CodeIgniter\HTTP\ResponseInterface
    {
        $filters = array_filter([
            'status' => $this->request->getGet('status'),
            'name'   => $this->request->getGet('name'),
            'q'      => $this->request->getGet('q'),
        ]);

        $pageGet = $this->request->getGet('page');
        if ($pageGet !== null && $pageGet !== '') {
            $page    = max(1, (int) $pageGet);
            $perPage = (int) ($this->request->getGet('per_page') ?: 15);
            $result  = $this->service->listPaginated($filters, $page, $perPage);

            return $this->respond([
                'data' => $result['items'],
                'meta' => $result['meta'],
            ], 200);
        }

        return $this->ok($this->service->list($filters));
    }

    public function show($id = null): \CodeIgniter\HTTP\ResponseInterface
    {
        try {
            return $this->ok($this->service->get((int) $id));
        } catch (\RuntimeException $e) {
            return $this->respond(['message' => $e->getMessage()], $this->exceptionHttpStatus($e, 400));
        }
    }

    public function create(): \CodeIgniter\HTTP\ResponseInterface
    {
        try {
            return $this->created($this->service->create($this->json()));
        } catch (\RuntimeException $e) {
            return $this->respond(['message' => $e->getMessage()], $this->exceptionHttpStatus($e, 422));
        }
    }

    public function update($id = null): \CodeIgniter\HTTP\ResponseInterface
    {
        try {
            return $this->ok($this->service->update((int) $id, $this->json()));
        } catch (\RuntimeException $e) {
            return $this->respond(['message' => $e->getMessage()], $this->exceptionHttpStatus($e, 422));
        }
    }

    public function softDelete($id = null): \CodeIgniter\HTTP\ResponseInterface
    {
        try {
            return $this->ok($this->service->softDelete((int) $id));
        } catch (\RuntimeException $e) {
            return $this->respond(['message' => $e->getMessage()], $this->exceptionHttpStatus($e, 400));
        }
    }
}
