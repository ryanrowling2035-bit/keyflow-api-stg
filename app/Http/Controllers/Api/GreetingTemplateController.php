<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GreetingTemplateRequest;
use App\Models\GreetingTemplate;
use App\Models\GreetingLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Exception;

class GreetingTemplateController extends Controller
{
    /**
     * Get paginated template list with filters
     */
    public function index(Request $request)
    {
        if (auth('api')->user()) {
            logUserActivity([
                'userId' => auth('api')->user()->id,
                'action' => 'view',
                'module' => 'Greeting Templates',
                'moduleId' => null,
                'description' => 'User viewed Greeting Templates list',
            ]);
        }
        $query = GreetingTemplate::query();

        if ($request->filled('search')) {
            $search = strtolower(trim($request->search));
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('LOWER(subject) LIKE ?', ["%{$search}%"]);
            });
        }

        if ($request->filled('type') && $request->type !== 'all') {
            $query->where('type', $request->type);
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $perPage = (int) $request->get('per_page', 10);
        if ($request->filled('target_audience') && $request->target_audience !== 'all') {
            $query->where('target_audience', $request->target_audience);
        }

        $templates = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $templates->items(),
            'meta'    => [
                'total'        => $templates->total(),
                'current_page' => $templates->currentPage(),
                'per_page'     => $templates->perPage(),
                'last_page'    => $templates->lastPage(),
            ],
        ]);
    }

    /**
     * Create a new template
     */
    public function store(GreetingTemplateRequest $request)
    {
        $validated = $request->validated();

        if ($validated['type'] !== 'Holiday') {
            $validated['holiday_date'] = null;
        }

        $template = GreetingTemplate::create($validated);

        if (auth('api')->user()) {
            logUserActivity([
                'userId' => auth('api')->user()->id,
                'action' => 'create',
                'module' => 'Greeting Templates',
                'moduleId' => $template->id,
                'description' => 'User created a new Greeting Template',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Greeting template created successfully',
            'data'    => $template,
        ], 201);
    }

    /**
     * Show single template
     */
    public function show($id)
    {
        $template = GreetingTemplate::findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $template,
        ]);
    }

    /**
     * Update template
     */
    public function update(Request $request, $id)
    {
        $template = GreetingTemplate::findOrFail($id);

        $validated = $request->validate([
            'name'          => 'sometimes|required|string|max:255',
            'type'          => 'sometimes|required|in:Birthday,Holiday',
            'trigger_event' => 'nullable|string',
            'holiday_date'  => 'nullable|date',
            'send_time'     => 'nullable|string',
            'status'        => 'sometimes|required|in:Active,Disabled',
            'target_audience' => 'sometimes|required|in:Clients,Employee',
            'subject'       => 'nullable|string',
            'body'          => 'nullable|string',
        ]);

        if (isset($validated['type']) && $validated['type'] !== 'Holiday') {
            $validated['holiday_date'] = null;
        }

        $template->update($validated);

        if (auth('api')->user()) {
            logUserActivity([
                'userId' => auth('api')->user()->id,
                'action' => 'update',
                'module' => 'Greeting Templates',
                'moduleId' => $template->id,
                'description' => 'User updated Greeting Template',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Greeting template updated successfully',
            'data'    => $template,
        ]);
    }

    /**
     * Soft delete template
     */
    public function destroy($id)
    {
        $template = GreetingTemplate::findOrFail($id);
        $template->delete();

        return response()->json([
            'success' => true,
            'message' => 'Greeting template deleted successfully',
        ]);
    }

    /**
     * Get greeting logs
     */
    public function logs(Request $request)
    {
        $query = GreetingLog::query();

        if ($request->filled('type') && $request->type !== 'all') {
            $query->where('type', $request->type);
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('template_id') && $request->template_id !== 'all') {
            $query->where('template_id', $request->template_id);
        }

        if ($request->filled('start_date')) {
            $query->whereDate('sent_at', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('sent_at', '<=', $request->end_date);
        }

        $perPage = (int) $request->get('per_page', 10);
        $logs = $query->orderBy('sent_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $logs->items(),
            'meta'    => [
                'total'        => $logs->total(),
                'current_page' => $logs->currentPage(),
                'per_page'     => $logs->perPage(),
                'last_page'    => $logs->lastPage(),
            ],
        ]);
    }

    /**
     * Trigger automated cron job manually
     */
    public function triggerCron()
    {
        try {
            Artisan::call('greetings:send-automated');
            return response()->json([
                'success' => true,
                'message' => 'Automated greeting check executed successfully.',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to run automated greeting check: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send a test email for a template
     */
    public function test(Request $request, $id)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        try {
            $template = GreetingTemplate::findOrFail($id);
            
            $sesKey = config('services.ses.key');
            $sesSecret = config('services.ses.secret');

            if (empty($sesKey) || empty($sesSecret)) {
                return response()->json([
                    'success' => false,
                    'message' => 'AWS SES credentials are not configured in .env. Greetings must strictly be sent via AWS SES and cannot use SMTP.',
                ], 422);
            }

            $mailable = new \App\Mail\GreetingTestMail($template);
            $customFromAddress = config('mail.greetings_from.address') ?: config('mail.from.address');
            $customFromName = config('mail.greetings_from.name') ?? config('mail.from.name');

            if (! empty($customFromAddress)) {
                $mailable->from($customFromAddress, $customFromName);
            }

            \Mail::mailer('ses')->to($request->email)->send($mailable);
            \Log::info('Greeting test email sent via AWS SES to: ' . $request->email);

            return response()->json([
                'success' => true,
                'message' => 'Test email sent successfully via AWS SES to ' . $request->email,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send test email: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function uploadImage(\Illuminate\Http\Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:5120',
        ]);

        try {
            $file = $request->file('image');
            $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $file->getClientOriginalName());
            $file->move(storage_path('app/greetings'), $filename);

            return response()->json([
                'success' => true,
                'data' => [
                    'url' => rtrim($request->getSchemeAndHttpHost(), '/') . '/api/greetings/image/' . $filename,
                ],
                'message' => 'Image uploaded successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function showImage($filename)
    {
        $path = storage_path('app/greetings/' . $filename);
        if (!file_exists($path)) {
            abort(404);
        }
        return response()->file($path);
    }

}








