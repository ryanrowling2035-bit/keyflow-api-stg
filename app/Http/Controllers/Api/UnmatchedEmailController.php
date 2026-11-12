<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContactEmail;
use App\Models\UnmatchedEmail;
use Illuminate\Http\Request;

class UnmatchedEmailController extends Controller
{
    /**
     * Get list of unmatched emails
     * Visible to owner user and admins/managers.
     */
    public function index(Request $request)
    {
        $query = UnmatchedEmail::query();

        $currentUser = user();
        if ($currentUser) {
            $isAdmin = $currentUser->hasRole('Admin') || $currentUser->hasRole('Super Admin') || $currentUser->hasRole('Manager');
            if (!$isAdmin) {
                $query->where(function ($q) use ($currentUser) {
                    $q->where('user_id', $currentUser->id)
                      ->orWhereNull('user_id');
                });
            }
        }

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('senderName', 'like', "%{$search}%")
                  ->orWhere('senderEmail', 'like', "%{$search}%")
                  ->orWhere('subject', 'like', "%{$search}%");
            });
        }

        $perPage = (int) $request->get('per_page', 10);
        $emails = $query->orderBy('dateReceived', 'desc')->orderBy('id', 'desc')->paginate($perPage);

        return response()->json([
            'data' => $emails->items(),
            'meta' => [
                'current_page' => $emails->currentPage(),
                'last_page' => $emails->lastPage(),
                'per_page' => $emails->perPage(),
                'total' => $emails->total(),
            ]
        ]);
    }

    /**
     * Link unmatched email to a contact
     */
    public function linkContact(Request $request, $id)
    {
        $request->validate([
            'contactId' => 'required'
        ]);

        $email = UnmatchedEmail::find($id);

        if (!$email) {
            return response()->json(['message' => 'Email not found'], 404);
        }

        $currentUser = user();

        // Add to ContactEmail
        ContactEmail::updateOrCreate(
            ['message_id' => $email->messageId],
            [
                'contact_id' => $request->contactId,
                'user_id' => $email->user_id ?? ($currentUser ? $currentUser->id : null),
                'sender_name' => $email->senderName,
                'sender_email' => $email->senderEmail,
                'subject' => $email->subject,
                'snippet' => $email->snippet,
                'body' => $email->body,
                'is_incoming' => true,
                'received_at' => $email->dateReceived,
            ]
        );

        // Remove from unmatched queue
        $email->delete();

        return response()->json([
            'success' => true,
            'message' => 'Email successfully linked to the contact.'
        ]);
    }

    /**
     * Ignore/Archive an unmatched email
     */
    public function destroy($id)
    {
        $email = UnmatchedEmail::find($id);

        if (!$email) {
            return response()->json(['message' => 'Email not found'], 404);
        }

        $email->delete();

        return response()->json([
            'success' => true,
            'message' => 'Email successfully ignored.'
        ]);
    }
}

