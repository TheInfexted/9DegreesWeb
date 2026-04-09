<?php

namespace App\Filters;

use App\Libraries\JWTHandler;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Exception;

class JWTFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null): mixed
    {
        $header = $request->getHeaderLine('Authorization');

        if (empty($header) || ! str_starts_with($header, 'Bearer ')) {
            return service('response')
                ->setStatusCode(401)
                ->setJSON(['message' => 'No token provided.']);
        }

        $token = substr($header, 7);

        try {
            $jwt             = new JWTHandler();
            $decoded         = $jwt->decode($token);
            $request->user   = $decoded;
        } catch (Exception $e) {
            return service('response')
                ->setStatusCode(401)
                ->setJSON(['message' => 'Invalid or expired token.']);
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): mixed
    {
        return null;
    }
}
