<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class WorkspaceController extends Controller
{
    /**
     * Display the workspace settings and member list.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $workspace = $user->currentWorkspace;

        if (!$workspace) {
            // If user has no current workspace, show their owned workspaces or prompts them to create one
            $ownedWorkspaces = $user->ownedWorkspaces;
            $memberWorkspaces = $user->workspaces;

            return Inertia::render('Reseller/Workspace/Index', [
                'ownedWorkspaces' => $ownedWorkspaces,
                'memberWorkspaces' => $memberWorkspaces,
                'currentWorkspace' => null,
            ]);
        }

        $members = $workspace->members()->get()->map(function ($member) {
            return [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'role' => $member->pivot->role,
                'permissions' => $member->pivot->permissions,
                'avatar' => $member->avatar,
            ];
        });

        $invitations = $workspace->invitations()->get();

        return Inertia::render('Reseller/Workspace/Index', [
            'currentWorkspace' => $workspace,
            'members' => $members,
            'invitations' => $invitations,
            'ownedWorkspaces' => $user->ownedWorkspaces,
            'memberWorkspaces' => $user->workspaces,
        ]);
    }

    /**
     * Create a new workspace.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        return DB::transaction(function () use ($request) {
            $user = $request->user();

            $workspace = Workspace::create([
                'name' => $request->name,
                'owner_id' => $user->id,
            ]);

            // Add owner as the first member
            WorkspaceMember::create([
                'workspace_id' => $workspace->id,
                'user_id' => $user->id,
                'role' => 'owner',
                'permissions' => ['generate', 'view', 'reset', 'access_api'],
            ]);

            // Set as current workspace if none
            if (!$user->current_workspace_id) {
                $user->update(['current_workspace_id' => $workspace->id]);
            }

            return back()->with('success', 'Workspace created successfully.');
        });
    }

    /**
     * Switch current active workspace.
     */
    public function switch(Request $request, Workspace $workspace)
    {
        $user = $request->user();

        // Verify user is a member or owner
        $isMember = WorkspaceMember::where('workspace_id', $workspace->id)
            ->where('user_id', $user->id)
            ->exists();

        if (!$isMember) {
            return back()->withErrors(['message' => 'You are not a member of this workspace.']);
        }

        $user->update(['current_workspace_id' => $workspace->id]);

        return back()->with('success', "Switched to {$workspace->name}.");
    }

    /**
     * Update member permissions or role.
     */
    public function updateMember(Request $request, Workspace $workspace, User $member)
    {
        $request->validate([
            'role' => 'required|string|in:manager,reseller',
            'permissions' => 'array',
        ]);

        $user = $request->user();

        // Security: Only owner or manager can update members
        $authUserMembership = WorkspaceMember::where('workspace_id', $workspace->id)
            ->where('user_id', $user->id)
            ->first();

        if (!$authUserMembership || !in_array($authUserMembership->role, ['owner', 'manager'])) {
            return back()->withErrors(['message' => 'Unauthorized. Only owners and managers can modify members.']);
        }

        $targetMembership = WorkspaceMember::where('workspace_id', $workspace->id)
            ->where('user_id', $member->id)
            ->first();

        if (!$targetMembership || $targetMembership->role === 'owner') {
            return back()->withErrors(['message' => 'You cannot modify the owner.']);
        }

        $targetMembership->update([
            'role' => $request->role,
            'permissions' => $request->permissions,
        ]);

        return back()->with('success', "Member updated successfully.");
    }

    /**
     * Remove a member from the workspace.
     */
    public function removeMember(Workspace $workspace, User $member)
    {
        $user = auth()->user();

        // Security: Only owner or manager can remove members
        $authUserMembership = WorkspaceMember::where('workspace_id', $workspace->id)
            ->where('user_id', $user->id)
            ->first();

        if (!$authUserMembership || !in_array($authUserMembership->role, ['owner', 'manager'])) {
            return back()->withErrors(['message' => 'Unauthorized. Only owners and managers can remove members.']);
        }

        $targetMembership = WorkspaceMember::where('workspace_id', $workspace->id)
            ->where('user_id', $member->id)
            ->first();

        if (!$targetMembership || $targetMembership->role === 'owner') {
            return back()->withErrors(['message' => 'You cannot remove the owner.']);
        }

        $targetMembership->delete();

        // If target user was in this workspace as current, clear it
        if ($member->current_workspace_id == $workspace->id) {
            $member->update(['current_workspace_id' => null]);
        }

        return back()->with('success', "Member removed from workspace.");
    }

    /**
     * Delete a workspace.
     */
    public function destroy(Workspace $workspace)
    {
        $user = auth()->user();

        // Security: Only owner can delete the workspace
        if ($workspace->owner_id !== $user->id) {
            return back()->withErrors(['message' => 'Only the owner can delete this workspace.']);
        }

        // Clean up: users who have this as current workspace
        User::where('current_workspace_id', $workspace->id)->update(['current_workspace_id' => null]);
        
        // Members who are sub-resellers should have their parent_id cleared? 
        // Actually, if it's a reseller team, the parent_id is the owner. 
        // We'll keep parent_id for commissions if they were linked, but maybe clear if they only existed within this context.
        // For now, we'll just delete the workspace and let the cascading handles members/invitations (if set in DB).
        // Let's manually clear parent_id for sub-resellers in this team if they are ONLY resellers here.

        return DB::transaction(function () use ($workspace) {
            $workspace->delete();
            return redirect()->route('reseller.workspace.index')->with('success', 'Workspace deleted successfully.');
        });
    }
}
