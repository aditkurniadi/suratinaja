<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Letters\CreateLetterAction;
use App\Actions\Letters\ReviseLetterAction;
use App\Actions\Letters\TransitionLetterStatusAction;
use App\Exceptions\DomainException;
use App\Http\Controllers\Controller;
use App\Models\Letter;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LetterController extends Controller
{
    /**
     * GET /api/v1/workspaces/{workspace}/letters
     */
    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->query('per_page', config('suratinaja.letters.per_page', 15))));
        $status = $request->query('status');
        $search = trim((string) $request->query('search', ''));

        $query = Letter::withoutGlobalScopes()
            ->with(['activeNumber', 'latestNumber'])
            ->where('workspace_id', $workspace->id)
            ->orderByDesc('id');

        if ($status !== null && $status !== '') {
            $query->where('status', strtolower((string) $status));
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('perihal', 'like', "%{$search}%")
                    ->orWhereHas('numbers', fn ($nq) => $nq->where('formatted_number', 'like', "%{$search}%"));
            });
        }

        $paginator = $query->paginate($perPage);

        $data = collect($paginator->items())->map(fn (Letter $l) => [
            'id' => $l->id,
            'letter_number' => $l->formatted_number,
            'perihal' => $l->perihal,
            'status' => $l->status->value,
            'created_at' => $l->created_at?->toIso8601ZuluString(),
        ])->values()->all();

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * POST /api/v1/workspaces/{workspace}/letters
     */
    public function store(
        Request $request,
        Workspace $workspace,
        CreateLetterAction $action,
    ): JsonResponse {
        $letter = $action->execute($workspace, $request->user(), $request->all());

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $letter->id,
                'letter_number' => $letter->formatted_number,
                'status' => $letter->status->value,
                'perihal' => $letter->perihal,
                'template_id' => $letter->template_id,
                'workspace_id' => $letter->workspace_id,
                'created_at' => $letter->created_at?->toIso8601ZuluString(),
            ],
            'message' => 'Draft surat berhasil dibuat.',
        ], 201);
    }

    /**
     * PATCH /api/v1/workspaces/{workspace}/letters/{letter}/status
     */
    public function updateStatus(
        Request $request,
        Workspace $workspace,
        int $letterId,
        TransitionLetterStatusAction $action,
    ): JsonResponse {
        $letter = $this->findWorkspaceLetter($workspace, $letterId);
        $updated = $action->execute($letter, $request->user(), (string) $request->input('status', ''));

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $updated->id,
                'status' => $updated->status->value,
                'signed_at' => $updated->signed_at?->toIso8601ZuluString(),
                'updated_at' => $updated->updated_at?->toIso8601ZuluString(),
            ],
            'message' => 'Status surat diperbarui.',
        ]);
    }

    /**
     * POST /api/v1/workspaces/{workspace}/letters/{letter}/revise
     */
    public function revise(
        Request $request,
        Workspace $workspace,
        int $letterId,
        ReviseLetterAction $action,
    ): JsonResponse {
        $letter = $this->findWorkspaceLetter($workspace, $letterId);
        $result = $action->execute($letter, $request->user(), $request->all());

        return response()->json([
            'success' => true,
            'data' => [
                'letter_id' => $result['letter']->id,
                'old_number' => $result['old_number'],
                'old_number_status' => $result['old_number_status'],
                'new_number' => $result['new_number'],
                'revision_reason' => $result['revision']->reason,
            ],
            'message' => 'Revisi surat tercatat.',
        ]);
    }

    private function findWorkspaceLetter(Workspace $workspace, int $letterId): Letter
    {
        $letter = Letter::withoutGlobalScopes()
            ->where('workspace_id', $workspace->id)
            ->find($letterId);

        if (! $letter) {
            throw DomainException::workspaceForbidden('Surat tidak ditemukan pada workspace ini.');
        }

        return $letter;
    }
}
