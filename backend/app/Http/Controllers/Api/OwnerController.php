<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\OwnerRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class OwnerController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(User::role('shop_owner')->withCount('shops')->latest()->paginate(50));
    }

    public function store(OwnerRequest $request): JsonResponse
    {
        $user = DB::transaction(function () use ($request): User {
            $user = User::create($request->validated());
            $user->assignRole('shop_owner');

            return $user;
        });

        return response()->json(new UserResource($user), 201);
    }

    public function update(OwnerRequest $request, User $owner): JsonResponse
    {
        abort_unless($owner->hasRole('shop_owner') && ! $owner->hasRole('admin'), 404);
        $data = $request->validated();
        if (empty($data['password'])) {
            unset($data['password']);
        }
        DB::transaction(function () use ($owner, $data): void {
            $owner->update($data);
            if (isset($data['password'])) {
                $owner->tokens()->delete();
            }
        });

        return response()->json(new UserResource($owner));
    }

    public function destroy(User $owner): Response
    {
        abort_unless($owner->hasRole('shop_owner') && ! $owner->hasRole('admin'), 404);
        abort_if($owner->shops()->exists(), 422, 'Reassign or delete the shops before deleting this owner.');
        DB::transaction(function () use ($owner): void {
            $owner->tokens()->delete();
            $owner->delete();
        });

        return response()->noContent();
    }
}
