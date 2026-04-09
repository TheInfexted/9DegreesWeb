<?php

namespace App\Controllers\Api;

use App\Models\RoleModel;

class RoleController extends BaseApiController
{
    private RoleModel $roleModel;

    public function __construct()
    {
        $this->roleModel = new RoleModel();
    }

    public function index(): \CodeIgniter\HTTP\ResponseInterface
    {
        return $this->ok($this->roleModel->findAll());
    }

    public function create(): \CodeIgniter\HTTP\ResponseInterface
    {
        $data = $this->json();
        if (empty($data['name'])) {
            return $this->badRequest('Name is required.');
        }
        $id = $this->roleModel->insert($data, true);
        return $this->created($this->roleModel->find($id));
    }

    public function update($id = null): \CodeIgniter\HTTP\ResponseInterface
    {
        $role = $this->roleModel->find($id);
        if (!$role) return $this->notFound('Role not found.');

        $this->roleModel->update($id, $this->json());
        return $this->ok($this->roleModel->find($id));
    }
}
