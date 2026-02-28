<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Ticket;
use App\Models\TicketMessage;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class TicketController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $tickets = Ticket::where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->get();

        $statusCounts = [
            'All' => $tickets->count(),
            'Open' => $tickets->where('status', 'Open')->count(),
            'Answered' => $tickets->where('status', 'Answered')->count(),
            'Closed' => $tickets->where('status', 'Closed')->count(),
        ];

        $purchases = \App\Models\Purchase::with('product')
            ->where('user_id', Auth::id())
            ->get();

        return Inertia::render('Support/Index', [
            'tickets' => $tickets,
            'statusCounts' => $statusCounts,
            'purchases' => $purchases,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'subject' => 'required|string|max:255',
            'category' => 'required|in:billing,hwid_reset,technical,product,other',
            'message' => 'required|string',
            'order_id' => 'required_if:category,hwid_reset|nullable|string',
            'attachment' => 'nullable|file|max:5120', // 5MB limit
        ]);

        $typeMap = [
            'billing' => 'Billing',
            'hwid_reset' => 'HWID Reset',
            'technical' => 'Support',
            'product' => 'Support',
            'other' => 'Other'
        ];

        $ticket = Ticket::create([
            'subject' => $request->subject,
            'type' => $typeMap[$request->category] ?? 'Other',
            'priority' => 'Normal',
            'message' => $request->message,
            'order_id' => $request->order_id,
            'user_id' => Auth::id(),
            'status' => 'Open',
        ]);

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('tickets', 'public');
        }

        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => Auth::id(),
            'message' => $request->message,
            'is_support' => false,
            'attachment' => $attachmentPath,
        ]);

        return redirect()->back()->with('success', "Ticket #{$ticket->id} created successfully!");
    }

    /**
     * Display the specified resource.
     */
    public function show(Ticket $ticket)
    {
        // Ensure user can only see their own tickets
        if ($ticket->user_id !== Auth::id()) {
            abort(403);
        }

        return response()->json([
            'success' => true,
            'ticket' => $ticket,
            'messages' => $ticket->messages()->with('user:id,name')->get(),
        ]);
    }

    /**
     * Add a reply to the ticket.
     */
    public function reply(Request $request, Ticket $ticket)
    {
        if ($ticket->user_id !== Auth::id()) {
            abort(403);
        }

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
            'is_support' => false,
            'attachment' => $attachmentPath,
        ]);

        $ticket->update([
            'status' => 'Open',
            'updated_at' => now(),
        ]);

        return redirect()->back();
    }
}
