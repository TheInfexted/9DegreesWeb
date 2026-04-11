<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;

abstract class BaseApiController extends ResourceController
{
    protected $format = 'json';

    protected function ok(mixed $data): \CodeIgniter\HTTP\ResponseInterface
    {
        return $this->respond(['data' => $data], 200);
    }

    protected function created(mixed $data): \CodeIgniter\HTTP\ResponseInterface
    {
        return $this->respond(['data' => $data], 201);
    }

    protected function noContent(): \CodeIgniter\HTTP\ResponseInterface
    {
        return $this->respond(null, 204);
    }

    protected function notFound(string $message = 'Not found.'): \CodeIgniter\HTTP\ResponseInterface
    {
        return $this->respond(['message' => $message], 404);
    }

    protected function badRequest(string $message): \CodeIgniter\HTTP\ResponseInterface
    {
        return $this->respond(['message' => $message], 400);
    }

    /**
     * Never use raw DB driver codes (e.g. MySQL 1452) as HTTP status.
     */
    protected function exceptionHttpStatus(\Throwable $e, int $fallback = 422): int
    {
        $code = (int) $e->getCode();

        return ($code >= 400 && $code <= 599) ? $code : $fallback;
    }

    protected function currentUser(): object
    {
        return $this->request->user;
    }

    protected function json(): array
    {
        // JSON body (production path)
        $json = $this->request->getJSON(true);
        if (!empty($json)) {
            return (array) $json;
        }

        // Form-encoded POST
        $post = $this->request->getPost();
        if (!empty($post)) {
            return $post;
        }

        // PUT/PATCH raw body (form-encoded or otherwise)
        $raw = $this->request->getRawInput();
        if (!empty($raw)) {
            if (is_string($raw)) {
                parse_str($raw, $parsed);
                return $parsed ?: [];
            }
            return (array) $raw;
        }

        return [];
    }
}
