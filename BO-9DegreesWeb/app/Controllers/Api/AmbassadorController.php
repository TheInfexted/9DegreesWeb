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
        ]);
        return $this->ok($this->service->list($filters));
    }

    public function show($id = null): \CodeIgniter\HTTP\ResponseInterface
    {
        try {
            return $this->ok($this->service->get((int) $id));
        } catch (\RuntimeException $e) {
            return $this->respond(['message' => $e->getMessage()], $e->getCode() ?: 400);
        }
    }

    public function create(): \CodeIgniter\HTTP\ResponseInterface
    {
        try {
            return $this->created($this->service->create($this->json()));
        } catch (\RuntimeException $e) {
            return $this->respond(['message' => $e->getMessage()], $e->getCode() ?: 400);
        }
    }

    public function update($id = null): \CodeIgniter\HTTP\ResponseInterface
    {
        try {
            return $this->ok($this->service->update((int) $id, $this->json()));
        } catch (\RuntimeException $e) {
            return $this->respond(['message' => $e->getMessage()], $e->getCode() ?: 400);
        }
    }

    public function softDelete($id = null): \CodeIgniter\HTTP\ResponseInterface
    {
        try {
            return $this->ok($this->service->softDelete((int) $id));
        } catch (\RuntimeException $e) {
            return $this->respond(['message' => $e->getMessage()], $e->getCode() ?: 400);
        }
    }
}
