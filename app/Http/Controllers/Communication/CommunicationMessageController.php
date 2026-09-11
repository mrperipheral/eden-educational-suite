<?php

namespace App\Http\Controllers\Communication;

use App\Events\CommunicationMessageAdded;
use App\Http\Controllers\Controller;
use App\Http\Requests\Communication\MessageRequest;
use App\Models\CommunicationMessage;
use App\Models\CommunicationThread;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/**
 * Reply to a Communication Hub thread. `{thread}` is resolved by
 * tenant-scoped `findOrFail`, so another school's id 404s.
 */
class CommunicationMessageController extends Controller
{
    public function store(MessageRequest $request, int $thread): RedirectResponse
    {
        $thread = CommunicationThread::query()->findOrFail($thread);

        DB::transaction(function () use ($request, $thread) {
            $message = new CommunicationMessage(['body' => $request->body()]);
            $message->sender_id = $request->user()->getKey();
            $thread->messages()->save($message);
            $thread->forceFill(['last_message_at' => $message->created_at])->save();

            event(new CommunicationMessageAdded($thread, $message));
        });

        return to_route('communication.threads.show', $thread)->with('status', __('Reply added.'));
    }
}
