<?php

namespace App\Http\Controllers\Communication;

use App\Enums\CommunicationCategory;
use App\Enums\CommunicationStatus;
use App\Enums\Permission;
use App\Events\CommunicationMessageAdded;
use App\Http\Controllers\Controller;
use App\Http\Requests\Communication\ThreadRequest;
use App\Http\Requests\Communication\UpdateThreadRequest;
use App\Models\CommunicationMessage;
use App\Models\CommunicationThread;
use App\Models\Guardian;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * The Communication Hub inbox — a shared, school-wide list of
 * `CommunicationThread`s. Staff-facing: gated `communication.view` /
 * `.create` / `.manage`, behind `module:notifications`. Every viewer with
 * `communication.view` sees every thread in the school (a shared inbox, like
 * the rest of the app's staff lists) — recipient-only visibility applies to
 * notifications, not this internal log. `{thread}` is resolved by
 * tenant-scoped `findOrFail`, so another school's id 404s.
 */
class CommunicationThreadController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('communication.view');

        $status = CommunicationStatus::tryFrom((string) $request->query('status'));
        $category = CommunicationCategory::tryFrom((string) $request->query('category'));

        $threads = CommunicationThread::query()
            ->with(['student:id,first_name,last_name,preferred_name', 'guardian:id,first_name,middle_name,last_name', 'assignedTo:id,name'])
            ->status($status)
            ->category($category)
            ->ordered()
            ->paginate(20)
            ->withQueryString();

        return view('communication.threads.index', [
            'threads' => $threads,
            'filters' => ['status' => $status, 'category' => $category],
            'statuses' => CommunicationStatus::all(),
            'categories' => CommunicationCategory::all(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('communication.create');

        return view('communication.threads.create', [
            'categories' => CommunicationCategory::all(),
            'students' => Student::query()->ordered()->get(['id', 'first_name', 'last_name', 'preferred_name']),
            'guardians' => Guardian::query()->ordered()->get(['id', 'first_name', 'middle_name', 'last_name']),
        ]);
    }

    public function store(ThreadRequest $request): RedirectResponse
    {
        $thread = DB::transaction(function () use ($request) {
            $thread = new CommunicationThread($request->payload());
            $thread->created_by = $request->user()->getKey();
            $thread->save();

            $message = new CommunicationMessage(['body' => $request->body()]);
            $message->sender_id = $request->user()->getKey();
            $thread->messages()->save($message);
            $thread->forceFill(['last_message_at' => $message->created_at])->save();

            event(new CommunicationMessageAdded($thread, $message));

            return $thread;
        });

        return to_route('communication.threads.show', $thread)->with('status', __('Communication logged.'));
    }

    public function show(int $thread): View
    {
        $this->authorize('communication.view');

        $thread = CommunicationThread::query()
            ->with([
                'student:id,first_name,last_name,preferred_name',
                'guardian:id,first_name,middle_name,last_name,email,phone',
                'createdBy:id,name',
                'assignedTo:id,name',
                'messages' => fn ($q) => $q->with('sender:id,name')->orderBy('created_at'),
            ])
            ->findOrFail($thread);

        return view('communication.threads.show', [
            'thread' => $thread,
            'canManage' => request()->user()->hasPermission(Permission::CommunicationManage),
            'canResolve' => request()->user()->hasPermission(Permission::CommunicationResolve),
            'canEscalate' => request()->user()->hasPermission(Permission::CommunicationEscalate),
        ]);
    }

    public function update(UpdateThreadRequest $request, int $thread): RedirectResponse
    {
        $thread = CommunicationThread::query()->findOrFail($thread);
        $thread->update($request->payload());

        return to_route('communication.threads.show', $thread)->with('status', __('Thread updated.'));
    }
}
