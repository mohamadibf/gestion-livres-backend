<?php

namespace App\Http\Controllers;

use App\Http\Resources\LoanResource;
use App\Models\Book;
use App\Models\Loan;
use App\Services\LoanService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LoanController extends Controller
{
    public function __construct(private LoanService $loans)
    {
    }

    public function index(Request $request)
    {
        $filters = $request->validate([
            'scope' => ['nullable', Rule::in(['mine', 'all'])],
            'status' => ['nullable', Rule::in(['active', 'returned'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $user = $request->user();
        $scope = $filters['scope'] ?? 'mine';

        // La vue de tous les emprunts est réservée aux admins
        abort_if($scope === 'all' && ! $user->isAdmin(), 403, 'Réservé aux administrateurs.');

        $query = Loan::query()->with(['book', 'user']);

        if ($scope === 'mine') {
            $query->where('user_id', $user->id);
        }

        match ($filters['status'] ?? null) {
            'active' => $query->active()->orderBy('due_at'),
            'returned' => $query->whereNotNull('returned_at')->orderByDesc('returned_at'),
            default => $query->orderByRaw('returned_at is null desc')->orderByDesc('borrowed_at'),
        };

        return LoanResource::collection($query->orderByDesc('id')->paginate($filters['per_page'] ?? 10)->withQueryString());
    }

    public function store(Request $request, Book $book)
    {
        $loan = $this->loans->borrow($request->user(), $book);

        return (new LoanResource($loan->load('book', 'user')))->response()->setStatusCode(201);
    }

    public function return(Request $request, Loan $loan)
    {
        abort_unless($request->user()->isAdmin() || $loan->user_id === $request->user()->id, 403);

        return new LoanResource($this->loans->return($loan)->load('book', 'user'));
    }

    public function renew(Request $request, Loan $loan)
    {
        // Seul l'emprunteur prolonge son prêt
        abort_unless($loan->user_id === $request->user()->id, 403);

        return new LoanResource($this->loans->renew($loan)->load('book', 'user'));
    }
}
