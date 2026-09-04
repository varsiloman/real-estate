<?php

namespace App\Http\Controllers;

use App\Enums\ImportStatus;
use App\Http\Requests\StoreSupplierImportRequest;
use App\Jobs\ProcessSupplierImport;
use App\Models\Import;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class SupplierImportController extends Controller
{
    public function store(StoreSupplierImportRequest $request, Supplier $supplier): JsonResponse
    {
        $offers = $request->validated('offers');

        $import = DB::transaction(function () use ($supplier, $offers): Import {
            $import = $supplier->imports()->create([
                'status' => ImportStatus::Queued,
            ]);

            ProcessSupplierImport::dispatch($import->id, $offers)->afterCommit();

            return $import;
        });

        return response()->json([
            'data' => [
                'id' => $import->id,
                'status' => $import->status->value,
            ],
        ], Response::HTTP_ACCEPTED);
    }
}
