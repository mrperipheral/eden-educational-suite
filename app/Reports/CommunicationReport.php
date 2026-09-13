<?php

namespace App\Reports;

use App\Enums\AnnouncementStatus;
use App\Models\Announcement;
use App\Models\CommunicationThread;
use Illuminate\Support\Collection;

/**
 * Communication (M18) reporting — a lightweight administrative summary
 * only (threads by status/category, published announcements, recent
 * activity). No BI system around communications — see
 * `docs/reporting.md` §12.
 */
class CommunicationReport
{
    /**
     * @return array{threads_total:int, threads_by_status: array<string,int>, threads_by_category: array<string,int>, announcements_published:int, recent_threads: Collection}
     */
    public function summary(): array
    {
        // ->toBase() strips Eloquent hydration/casting before pluck() —
        // `status`/`category` are cast to enums on the model, and an enum
        // instance cannot be used as a PHP array key. The global tenant
        // scope is still applied (already compiled into the query by the
        // time toBase() runs), only the casting is bypassed.
        $byStatus = CommunicationThread::query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->toBase()->pluck('total', 'status')->all();
        $byCategory = CommunicationThread::query()->selectRaw('category, COUNT(*) as total')->groupBy('category')->toBase()->pluck('total', 'category')->all();

        $recentThreads = CommunicationThread::query()
            ->with(['student:id,first_name,last_name', 'assignedTo:id,name'])
            ->ordered()
            ->limit(10)
            ->get();

        return [
            'threads_total' => CommunicationThread::query()->count(),
            'threads_by_status' => $byStatus,
            'threads_by_category' => $byCategory,
            'announcements_published' => Announcement::query()->where('status', AnnouncementStatus::Published->value)->count(),
            'recent_threads' => $recentThreads,
        ];
    }
}
