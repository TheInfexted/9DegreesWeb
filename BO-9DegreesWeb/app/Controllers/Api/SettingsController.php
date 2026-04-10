<?php

namespace App\Controllers\Api;

use App\Models\SettingsModel;
use App\Services\AuthService;

class SettingsController extends BaseApiController
{
    private SettingsModel $settingsModel;
    private AuthService $authService;

    public function __construct()
    {
        $this->settingsModel = new SettingsModel();
        $this->authService   = new AuthService();
    }

    public function index(): \CodeIgniter\HTTP\ResponseInterface
    {
        return $this->ok($this->settingsModel->getAll());
    }

    public function update($id = null): \CodeIgniter\HTTP\ResponseInterface
    {
        $allowedKeys = ['company_name', 'company_address', 'company_registration', 'company_phone', 'company_email'];
        $data        = array_intersect_key($this->json(), array_flip($allowedKeys));
        return $this->ok($this->settingsModel->setValues($data));
    }

    public function changePassword(): \CodeIgniter\HTTP\ResponseInterface
    {
        try {
            $data   = $this->json();
            $userId = $this->currentUser()->user_id;
            $this->authService->changePassword(
                $userId,
                $data['current_password'] ?? '',
                $data['new_password'] ?? ''
            );
            return $this->ok(['message' => 'Password updated successfully.']);
        } catch (\RuntimeException $e) {
            return $this->respond(['message' => $e->getMessage()], $e->getCode() ?: 400);
        }
    }
}
