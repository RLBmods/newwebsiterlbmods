<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class TicketController extends Controller
{
    /**
     * Display a listing of tickets.
     */
    public function index()
    {
        $status = request('status', 'All');
        
        $query = Ticket::with(['user:id,name,email'])
            ->orderBy('created_at', 'desc');

        if ($status !== 'All') {
            $query->where('status', $status);
        }

        $tickets = $query->paginate(15)->withQueryString();

        $statusCounts = [
            'All' => Ticket::count(),
            'Open' => Ticket::where('status', 'Open')->count(),
            'Answered' => Ticket::where('status', 'Answered')->count(),
            'Closed' => Ticket::where('status', 'Closed')->count(),
        ];

        return Inertia::render('Admin/Tickets/Index', [
            'tickets' => $tickets,
            'statusCounts' => $statusCounts,
            'filters' => [
                'status' => $status
            ]
        ]);
    }

    /**
     * Display the specified ticket.
     */
    public function show(Ticket $ticket)
    {
        return Inertia::render('Admin/Tickets/Show', [
            'ticket' => $ticket->load(['user:id,name,email']),
            'messages' => $ticket->messages()->with('user:id,name,email')->get(),
        ]);
    }

    /**
     * Add a reply to the ticket.
     */
    public function reply(Request $request, Ticket $ticket)
    {
        $request->validate([
            'message' => 'required|string',
            'attachment' => 'nullable|file|max:5120',
        ]);

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('tickets', 'public');
        }

        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => Auth::id(),
            'message' => $request->message,
            'is_support' => true,
            'attachment' => $attachmentPath,
        ]);

        $ticket->update([
            'status' => 'Answered',
            'updated_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Reply sent successfully.');
    }

    /**
     * Update the ticket status.
     */
    public function updateStatus(Request $request, Ticket $ticket)
    {
        $request->validate([
            'status' => 'required|in:Open,Answered,Closed',
        ]);

        $ticket->update([
            'status' => $request->status,
        ]);

        return redirect()->back()->with('success', "Ticket status updated to {$request->status}.");
    }
}
