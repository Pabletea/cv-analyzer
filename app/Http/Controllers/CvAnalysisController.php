<?php

namespace App\Http\Controllers;

use App\Models\CvAnalysis;
use App\Jobs\AnalyzeCvJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CvAnalysisController extends Controller
{
    public function analyze(Request $request): JsonResponse
    {
        $request->validate([
            'cv' => 'required|file|mimes:pdf,txt|max:5120',
        ]);

        $file = $request->file('cv');

        $analysis = CvAnalysis::create([
            'filename' => $file->getClientOriginalName(),
            'status'   => 'pending',
        ]);

        $filePath = $file->store('cvs', 'local');

        AnalyzeCvJob::dispatch($analysis, $filePath);

        return response()->json([
            'id'      => $analysis->id,
            'status'  => $analysis->status,
            'message' => 'CV recibido correctamente. Procesando análisis.',
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
        $analysis = CvAnalysis::findOrFail($id);

        if (!$analysis->isCompleted()) {
            return response()->json([
                'message' => 'El análisis aún no está disponible.',
                'status'  => $analysis->status,
            ], 422);
        }

        return response()->json([
            'id'       => $analysis->id,
            'filename' => $analysis->filename,
            'result'   => $analysis->result,
        ]);
    }
}