<?php

namespace App\Http\Controllers;

use App\Models\CvAnalysis;
use App\Jobs\AnalyzeCvJob;
use App\Models\AnalysisConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\CvBatch;

class CvAnalysisController extends Controller
{
    public function analyze(Request $request): JsonResponse
    {
        $request->validate([
            'cv' => 'required|file|mimes:pdf,txt|max:5120',
            'config_id' => 'nullable|exists:analysis_configs,id',
        ]);

        $file = $request->file('cv');
        $config = $request->config_id
            ? AnalysisConfig::find($request->config_id)
            : null;

        $analysis = CvAnalysis::create([
            'filename' => $file->getClientOriginalName(),
            'status'   => 'pending',
            'config_id' => $config?->id,
        ]);

        $filePath = $file->store('cvs', 'local');

        AnalyzeCvJob::dispatch($analysis, $filePath, $config);

        return response()->json([
            'id'      => $analysis->id,
            'status'  => $analysis->status,
            'message' => "CV received successfully. Processing analysis."
        ], 202);
    }

    public function status(int $id): JsonResponse
    {
        $analysis = CvAnalysis::findOrFail($id);

        return response()->json([
            'id'     => $analysis->id,
            'status' => $analysis->status,
        ]);
    }

    public function report(int $id): JsonResponse
    {
        $analysis = CvAnalysis::with('config')->findOrFail($id);

        if (!$analysis->isCompleted()) {
            return response()->json([
                'message' => 'El análisis aún no está disponible.',
                'status'  => $analysis->status,
            ], 422);
        }

        return response()->json([
            'id'       => $analysis->id,
            'filename' => $analysis->filename,
            'config'   => $analysis->config,
            'result'   => $analysis->result,
        ]);
    }

    public function createConfig(Request $request): JsonResponse{
        $data = $request->validate([
            'name'                  => 'required|string|max:100',
            'position'              => 'required|string|max:100',
            'prompt_extra'          => 'nullable|string|max:1000',
            'required_skills'       => 'nullable|array',
            'required_skills.*'     => 'string',
            'min_years_experience'  => 'nullable|integer|min:0',
        ]);

        $config = AnalysisConfig::create($data);

        return response()->json($config, 201);
    }

    public function listConfigs(): JsonResponse{
        return response()->json(AnalysisConfig::all());
    }

    public function analyzeBatch(Request $request): JsonResponse
    {
        $request->validate([
            'cvs'         => 'required|array|min:1|max:20',
            'cvs.*'       => 'required|file|mimes:pdf,txt|max:5120',
            'config_id'   => 'nullable|exists:analysis_configs,id',
        ]);

        $config = $request->config_id
            ? AnalysisConfig::find($request->config_id)
            : null;

        $batch = CvBatch::create([
            'config_id' => $config?->id,
            'status'    => 'processing',
            'total'     => count($request->file('cvs')),
            'processed' => 0,
        ]);

        foreach ($request->file('cvs') as $file) {
            $analysis = CvAnalysis::create([
                'filename'  => $file->getClientOriginalName(),
                'status'    => 'pending',
                'config_id' => $config?->id,
                'batch_id'  => $batch->id,
            ]);

            $filePath = $file->store('cvs', 'local');

            AnalyzeCvJob::dispatch($analysis, $filePath, $config);
        }

        return response()->json([
            'batch_id' => $batch->id,
            'total'    => $batch->total,
            'status'   => $batch->status,
            'message'  => "{$batch->total} CVs recieved. Processing them.",
        ], 202);
    }

    public function batchStatus(int $id): JsonResponse
    {
        $batch = CvBatch::findOrFail($id);

        return response()->json([
            'batch_id'  => $batch->id,
            'status'    => $batch->status,
            'total'     => $batch->total,
            'processed' => $batch->processed,
            'progress'  => round(($batch->processed / $batch->total) * 100) . '%',
        ]);
    }

    public function batchRanking(int $id): JsonResponse
    {
        $batch = CvBatch::with('config')->findOrFail($id);

        return response()->json([
            'batch_id'  => $batch->id,
            'status'    => $batch->status,
            'config'    => $batch->config,
            'total'     => $batch->total,
            'processed' => $batch->processed,
            'ranking'   => $batch->ranking(),
        ]);
    }
}