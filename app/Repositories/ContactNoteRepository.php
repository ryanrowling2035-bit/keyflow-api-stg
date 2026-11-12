<?php

namespace App\Repositories;

use App\Models\Contact;
use App\Models\ContactNote;

class ContactNoteRepository
{
    public function create(array $data)
    {
        $note = ContactNote::create($data);

        return $note->load(['creator:id,name,email,profilePhoto']);
    }

    public function findById($id)
    {
        return ContactNote::with(['creator:id,name,email,profilePhoto', 'replies.creator:id,name,email,profilePhoto'])->find($id);
    }

    public function update($id, array $data)
    {
        $note = ContactNote::findOrFail($id);
        $note->update($data);

        return $note->fresh(['creator:id,name,email,profilePhoto', 'replies.creator:id,name,email,profilePhoto']);
    }

    public function delete($id)
    {
        $note = ContactNote::findOrFail($id);

        // Delete child replies if this is a parent note
        ContactNote::where('parent', $id)->delete();

        return $note->delete();
    }

    public function getByContactId($contactId, $type = null)
    {
        $contact = Contact::find($contactId);

        // Notes written against a contact that was later merged into this one still
        // belong to this person — merging moved nothing, so read the whole family.
        // Falls back to the raw id if the contact is gone, so nothing 500s here.
        $contactIds = $contact ? $contact->aliasIds() : [$contactId];

        $query = ContactNote::with(['creator:id,name,email,profilePhoto', 'replies.creator:id,name,email,profilePhoto'])
            ->whereIn('contactId', $contactIds)
            ->where('parent', 0);

        if ($type) {
            $query->where('type', $type);
            $notes = $query->orderBy('id', 'desc')->get();

            if ($type === 'email') {
                $notes = $this->mergeContactEmails($contactId, $notes);
            }

            return $notes;
        }

        $allNotes = $query->orderBy('id', 'desc')->get();
        $allNotes = $this->mergeContactEmails($contactId, $allNotes);

        return collect($allNotes)->groupBy('type');
    }

    private function mergeContactEmails($contactId, $notes)
    {
        $contactEmails = \App\Models\ContactEmail::where('contact_id', $contactId)
            ->orderBy('received_at', 'desc')
            ->get();

        $contact = Contact::find($contactId);
        $contactEmailStr = $contact ? $contact->email : '';

        $mappedEmails = $contactEmails->map(function ($ce) use ($contactEmailStr) {
            return [
                'id' => 'ce_' . $ce->id,
                'contactId' => $ce->contact_id,
                'type' => 'email',
                'conversation_id' => $ce->conversation_id,
                'content' => [
                    'isFromOutlook' => true,
                    'direction' => $ce->is_incoming ? 'incoming' : 'outgoing',
                    'subject' => $ce->subject,
                    'from' => $ce->sender_name ? "{$ce->sender_name} <{$ce->sender_email}>" : $ce->sender_email,
                    'to' => $ce->is_incoming ? '' : $contactEmailStr,
                    'body' => $ce->body ?: $ce->snippet,
                ],
                'created_at' => $ce->received_at,
                'creator' => null,
                'replies' => []
            ];
        });

        return collect($notes)->concat($mappedEmails)->sortByDesc('created_at')->values();
    }
}
