<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMember;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class WorkspaceInvitationController extends Controller
{
    /**
     * Send an invitation to a user.
     */
    public function store(Request $request, Workspace $workspace)
    {
        $request->validate([
            'email' => 'required|email',
            'role' => 'required|string|in:manager,reseller',
            'permissions' => 'array',
        ]);

        $user = auth()->user();

        // Security check
        $membership = WorkspaceMember::where('workspace_id', $workspace->id)
            ->where('user_id', $user->id)
            ->first();

        if (!$membership || !in_array($membership->role, ['owner', 'manager'])) {
            return back()->withErrors(['message' => 'Unauthorized.']);
        }

        // Check if user is already a member
        $invitedUser = User::where('email', $request->email)->first();
        if ($invitedUser) {
            $isMember = WorkspaceMember::where('workspace_id', $workspace->id)
                ->where('user_id', $invitedUser->id)
                ->exists();
            if ($isMember) {
                return back()->withErrors(['message' => 'User is already a member of this workspace.']);
            }
        }

        // Create Invitation
        WorkspaceInvitation::create([
            'workspace_id' => $workspace->id,
            'email' => $request->email,
            'role' => $request->role,
            'permissions' => $request->permissions,
            'token' => Str::random(40),
            'expires_at' => now()->addDays(7),
        ]);

        // In a real app, you'd send an email here.
        // For this task, we'll assume the user just checks their dashboard/notifications.

        return back()->with('success', "Invitation sent to {$request->email}.");
    }

    /**
     * Accept an invitation.
     */
    public function accept(Request $request, string $token)
    {
        $invitation = WorkspaceInvitation::where('token', $token)
            ->where('expires_at', '>', now())
            ->firstOrFail();

        $user = $request->user();

        if ($user->email !== $invitation->email) {
            return redirect()->route('dashboard')->withErrors(['message' => 'This invitation was sent to a different email address.']);
        }

        return DB::transaction(function () use ($invitation, $user) {
            // Add as member
            WorkspaceMember::create([
                'workspace_id' => $invitation->workspace_id,
                'user_id' => $user->id,
                'role' => $invitation->role,
                'permissions' => $invitation->permissions,
            ]);

            // If role is reseller, link hierarchy
            if ($invitation->role === 'reseller') {
                $workspace = Workspace::find($invitation->workspace_id);
                $user->update([
                    'parent_id' => $workspace->owner_id,
                    'role' => 'reseller', // Automatically promote to reseller if invited as one?
                ]);
            }

            // Set as current workspace
            $user->update(['current_workspace_id' => $invitation->workspace_id]);

            // Delete invitation
            $invitation->delete();

            return redirect()->route('reseller.workspace.index')->with('success', 'You have joined the workspace.');
        });
    }

    /**
     * Cancel an invitation.
     */
    public function destroy(WorkspaceInvitation $invitation)
    {
        $user = auth()->user();
        $membership = WorkspaceMember::where('workspace_id', $invitation->workspace_id)
            ->where('user_id', $user->id)
            ->first();

        if (!$membership || !in_array($membership->role, ['owner', 'manager'])) {
            return back()->withErrors(['message' => 'Unauthorized.']);
        }

        $invitation->delete();

        return back()->with('success', 'Invitation cancelled.');
    }
}
