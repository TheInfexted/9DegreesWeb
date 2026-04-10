<?php

namespace App\Controllers\Api;

use App\Services\AccessService;

class AccessController extends BaseApiController
{
    private AccessService $accessService;

    public function __construct()
    {
        $this->accessService = new AccessService();
    }

    public function index(): \CodeIgniter\HTTP\ResponseInterface
    {
        return $this->ok($this->accessService->list());
    }

    public function create(): \CodeIgniter\HTTP\ResponseInterface
    {
        try {
            return $this->created($this->accessService->create($this->json()));
        } catch (\RuntimeException $e) {
            return $this->respond(['message' => $e->getMessage()], $e->getCode() ?: 400);
        }
    }

    public function update($id = null): \CodeIgniter\HTTP\ResponseInterface
    {
        try {
            return $this->ok($this->accessService->update((int) $id, $this->json()));
        } catch (\RuntimeException $e) {
            return $this->respond(['message' => $e->getMessage()], $e->getCode() ?: 400);
        }
    }

    public function delete($id = null): \CodeIgniter\HTTP\ResponseInterface
    {
        try {
            $this->accessService->delete((int) $id);
            return $this->noContent();
        } catch (\RuntimeException $e) {
            return $this->respond(['message' => $e->getMessage()], $e->getCode() ?: 400);
        }
    }
}
