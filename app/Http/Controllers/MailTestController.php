<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;

class MailTestController extends Controller
{
    public function index(): View
    {
        $logs = Activity::where('log_name', 'mail-test')
            ->latest()
            ->limit(20)
            ->get();

        return view('mail-test.index', compact('logs'));
    }

    public function send(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'to' => ['required', 'email'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
        ]);

        $status = 'success';

        try {
            Mail::raw($validated['message'], function ($m) use ($validated): void {
                $m->to($validated['to'])->subject($validated['subject']);
            });
        } catch (\Throwable) {
            $status = 'failed';
        }

        activity('mail-test')
            ->causedBy($request->user())
            ->withProperties([
                'to' => $validated['to'],
                'subject' => $validated['subject'],
                'status' => $status,
                'ip' => $request->ip(),
            ])
            ->log('test-sent');

        return $status === 'success'
            ? back()->with('success', "Test email sent to {$validated['to']}.")
            : back()->with('error', 'Mail send failed. Check your SMTP config.');
    }
}
