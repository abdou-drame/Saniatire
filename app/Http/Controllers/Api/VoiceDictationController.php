<?php

namespace App\Http\Controllers\Api;

use App\Domain\Ai\Models\VoiceDictation;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Étape 9 §4 / §7 : structure uniquement, aucune transcription réelle.
 * `status` reste `en_attente` après le dépôt du fichier — voir
 * app/Domain/Ai/README.md pour le point d'extension.
 */
class VoiceDictationController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:voice_dictation.create', only: ['store']),
            new Middleware('permission:voice_dictation.view', only: ['index', 'show']),
        ];
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'audio' => ['required', 'file', 'mimetypes:audio/mpeg,audio/wav,audio/x-wav,audio/webm,audio/mp4,audio/ogg', 'max:20480'],
            'consultation_id' => ['nullable', 'integer', 'exists:consultations,id'],
        ]);

        $path = $request->file('audio')->store('voice-dictations', 'local');

        $dictation = VoiceDictation::create([
            'structure_id' => $request->user()->structure_id,
            'user_id' => $request->user()->id,
            'consultation_id' => $data['consultation_id'] ?? null,
            'audio_path' => $path,
            'status' => 'en_attente',
        ]);

        return response()->json($dictation, 201);
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json(
            VoiceDictation::where('user_id', $request->user()->id)
                ->latest()
                ->paginate($request->integer('per_page', 20))
        );
    }

    public function show(VoiceDictation $voiceDictation): JsonResponse
    {
        return response()->json($voiceDictation);
    }
}
