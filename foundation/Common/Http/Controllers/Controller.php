<?php

declare(strict_types=1);

namespace Foundation\Common\Http\Controllers;

use Foundation\Common\Traits\HasPrincipal;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Routing\Controller as BaseController;

abstract class Controller extends BaseController
{
    use AuthorizesRequests;
    use HasPrincipal;

    protected function success(mixed $data = null, int $status = 200): JsonResponse
    {
        // A paginated resource collection carries its page items plus pagination
        // meta; surface both so clients page instead of loading every row.
        if ($data instanceof ResourceCollection && $data->resource instanceof AbstractPaginator) {
            $payload = $data->response()->getData(true);

            return response()->json([
                'success' => true,
                'data' => $payload['data'],
                'meta' => $payload['meta'] ?? null,
            ], $status);
        }

        return response()->json(['success' => true, 'data' => $data], $status);
    }

    protected function created(mixed $data = null): JsonResponse
    {
        return $this->success($data, 201);
    }

    protected function noContent(): JsonResponse
    {
        return response()->json(null, 204);
    }

    /** @param array<string, mixed>|null $errors */
    protected function error(string $message, int $status = 400, ?array $errors = null): JsonResponse
    {
        return response()->json(array_filter([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], fn ($v) => $v !== null), $status);
    }
}
