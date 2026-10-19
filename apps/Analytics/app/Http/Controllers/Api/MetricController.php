<?php

declare(strict_types=1);

namespace Apps\Analytics\Http\Controllers\Api;

use Apps\Analytics\Http\Requests\ReadMetricsRequest;
use Apps\Analytics\Models\Dataset;
use Apps\Analytics\Repositories\DatasetRepository;
use Foundation\Common\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class MetricController extends Controller
{
    public function index(ReadMetricsRequest $request, DatasetRepository $datasets): JsonResponse
    {
        $this->authorize('viewAny', Dataset::class);

        return $this->success($datasets->read($request->toDto()));
    }
}
