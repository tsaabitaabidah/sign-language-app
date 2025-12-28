<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * Display a listing of users.
     */
    public function index(): Response
    {
        $users = User::query()
            ->select('id', 'name', 'email', 'email_verified_at', 'created_at', 'updated_at')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return inertia('Admin/Users/Index', [
            'users' => [
                'data' => $users->items(),
                'links' => $users->linkCollection()->toArray(),
                'meta' => [
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                    'from' => $users->firstItem(),
                    'to' => $users->lastItem(),
                ],
            ],
        ]);
    }

    /**
     * Show the form for creating a new user.
     */
    public function create(): Response
    {
        return inertia('Admin/Users/Create');
    }

    /**
     * Store a newly created user in storage.
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8|confirmed',
            ], [
                'name.required' => 'Name is required',
                'name.max' => 'Name may not be greater than 255 characters',
                'email.required' => 'Email is required',
                'email.email' => 'Email must be a valid email address',
                'email.unique' => 'Email has already been taken',
                'password.required' => 'Password is required',
                'password.min' => 'Password must be at least 8 characters',
                'password.confirmed' => 'Password confirmation does not match',
            ]);

            if ($validator->fails()) {
                throw ValidationException::withMessages($validator->errors()->toArray());
            }

            $validated = $validator->validated();

            User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'email_verified_at' => now(), // Auto-verify for admin created users
            ]);

            return redirect()->route('admin.users.index')
                ->with('success', 'User created successfully!');

        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            \Log::error('User creation error: ' . $e->getMessage(), [
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->back()
                ->with('error', 'An error occurred while creating the user. Please try again.');
        }
    }

    /**
     * Display the specified user.
     */
    public function show(User $user): Response
    {
        return inertia('Admin/Users/Show', [
            'user' => $user,
        ]);
    }

    /**
     * Show the form for editing the specified user.
     */
    public function edit(User $user): Response
    {
        return inertia('Admin/Users/Edit', [
            'user' => $user,
        ]);
    }

    /**
     * Update the specified user in storage.
     */
    public function update(Request $request, User $user)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
                'password' => 'nullable|string|min:8|confirmed',
            ], [
                'name.required' => 'Name is required',
                'name.max' => 'Name may not be greater than 255 characters',
                'email.required' => 'Email is required',
                'email.email' => 'Email must be a valid email address',
                'email.unique' => 'Email has already been taken',
                'password.min' => 'Password must be at least 8 characters',
                'password.confirmed' => 'Password confirmation does not match',
            ]);

            if ($validator->fails()) {
                throw ValidationException::withMessages($validator->errors()->toArray());
            }

            $validated = $validator->validated();

            $updateData = [
                'name' => $validated['name'],
                'email' => $validated['email'],
            ];

            // Only update password if provided
            if (!empty($validated['password'])) {
                $updateData['password'] = Hash::make($validated['password']);
            }

            $user->update($updateData);

            return redirect()->route('admin.users.index')
                ->with('success', 'User updated successfully!');

        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            \Log::error('User update error: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->back()
                ->with('error', 'An error occurred while updating the user. Please try again.');
        }
    }

    /**
     * Remove the specified user from storage.
     */
    public function destroy(User $user): JsonResponse
    {
        try {
            // Prevent deletion of the current authenticated user
            if ($user->id === auth()->id()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Cannot delete yourself',
                    'message' => 'You cannot delete your own account.',
                ], 422);
            }

            $userName = $user->name;
            $user->delete();

            return response()->json([
                'success' => true,
                'message' => "User '{$userName}' deleted successfully!",
            ]);

        } catch (\Exception $e) {
            \Log::error('User deletion error: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Delete failed',
                'message' => 'An error occurred while deleting the user.',
            ], 500);
        }
    }

    /**
     * Toggle user email verification status.
     */
    public function toggleVerification(User $user): JsonResponse
    {
        try {
            $user->update([
                'email_verified_at' => $user->email_verified_at ? null : now(),
            ]);

            $status = $user->email_verified_at ? 'verified' : 'unverified';

            return response()->json([
                'success' => true,
                'message' => "User {$status} successfully!",
                'email_verified_at' => $user->email_verified_at,
            ]);

        } catch (\Exception $e) {
            \Log::error('User verification toggle error: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Toggle verification failed',
                'message' => 'An error occurred while toggling verification status.',
            ], 500);
        }
    }

    /**
     * Get users for API (AJAX requests).
     */
    public function getUsers(Request $request): JsonResponse
    {
        try {
            $query = User::query()
                ->select('id', 'name', 'email', 'email_verified_at', 'created_at', 'updated_at');

            // Search functionality
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            // Filter by verification status
            if ($request->has('verified') && $request->verified !== '') {
                if ($request->verified === '1') {
                    $query->whereNotNull('email_verified_at');
                } else {
                    $query->whereNull('email_verified_at');
                }
            }

            // Sort functionality
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            $users = $query->paginate(10);

            return response()->json([
                'success' => true,
                'data' => $users->items(),
                'pagination' => [
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                ],
            ]);

        } catch (\Exception $e) {
            \Log::error('Get users error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => 'Failed to load users',
                'message' => 'Unable to load users at this time.',
            ], 500);
        }
    }

    /**
     * Bulk delete users.
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'user_ids' => 'required|array|min:1',
                'user_ids.*' => 'exists:users,id',
            ], [
                'user_ids.required' => 'Please select users to delete',
                'user_ids.array' => 'Invalid user selection',
                'user_ids.min' => 'Please select at least one user',
                'user_ids.*.exists' => 'One or more selected users do not exist',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation failed',
                    'messages' => $validator->errors(),
                ], 422);
            }

            $userIds = $request->user_ids;
            $currentUserId = auth()->id();

            // Remove current user from deletion list if present
            $userIds = array_filter($userIds, function ($id) use ($currentUserId) {
                return $id != $currentUserId;
            });

            if (empty($userIds)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Cannot delete yourself',
                    'message' => 'You cannot include yourself in bulk deletion.',
                ], 422);
            }

            $deletedCount = User::whereIn('id', $userIds)->delete();

            return response()->json([
                'success' => true,
                'message' => "{$deletedCount} user(s) deleted successfully!",
                'deleted_count' => $deletedCount,
            ]);

        } catch (\Exception $e) {
            \Log::error('Bulk delete users error: ' . $e->getMessage(), [
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Bulk delete failed',
                'message' => 'An error occurred during bulk deletion.',
            ], 500);
        }
    }
}
