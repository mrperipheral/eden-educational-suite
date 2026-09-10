<?php

namespace Database\Seeders;

use App\Enums\AssignmentSubmissionStatus;
use App\Enums\AttendanceStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\GuardianRelationship;
use App\Enums\Module;
use App\Enums\Role;
use App\Enums\StudentStatus;
use App\Enums\TeacherStatus;
use App\Models\AcademicLevel;
use App\Models\AcademicSession;
use App\Models\Assessment;
use App\Models\AssessmentCategory;
use App\Models\Assignment;
use App\Models\AttendanceRegister;
use App\Models\Guardian;
use App\Models\School;
use App\Models\SchoolModule;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Timetable;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Local development data:
     *   - Alpha Academy: fully onboarded (admin, settings, current session).
     *   - Beta School: freshly provisioned (no admin, no settings, no session).
     *   - a platform admin, and a spread of per-school roles.
     */
    public function run(): void
    {
        $alpha = School::factory()->create(['name' => 'Alpha Academy', 'slug' => 'alpha-academy']);
        $beta = School::factory()->create(['name' => 'Beta School', 'slug' => 'beta-school']);

        User::factory()->platformAdmin()->create([
            'name' => 'Platform Owner',
            'email' => 'owner@example.com',
        ]);

        $admin = User::factory()->create(['name' => 'Test User', 'email' => 'test@example.com']);
        $admin->joinSchool($alpha, Role::SchoolAdmin);

        User::factory()->create(['name' => 'Priya Principal', 'email' => 'principal@example.com'])
            ->joinSchool($alpha, Role::Principal);

        $tomiwa = User::factory()->create(['name' => 'Tomiwa Teacher', 'email' => 'teacher@example.com']);
        $tomiwa->joinSchool($alpha, Role::Teacher);

        User::factory()->create(['name' => 'Bola Bursar', 'email' => 'bursar@example.com'])
            ->joinSchool($alpha, Role::Bursar);

        // Teacher at Alpha, Parent at Beta — the cross-school role case.
        $dual = User::factory()->create(['name' => 'Dele Dual', 'email' => 'dual@example.com']);
        $dual->joinSchool($alpha, Role::Staff);
        $dual->joinSchool($beta, Role::Parent);

        // Alpha's school-owned onboarding data (created inside its tenant context).
        $tenant = app(TenantContext::class);
        $tenant->set($alpha);

        $settings = $alpha->settings()->firstOrCreate([]);
        $settings->fill([
            'contact_email' => 'office@alpha.example',
            'contact_phone' => '+234 801 234 5678',
            'website_url' => 'https://alpha-academy.example',
            'address_line1' => '12 Bourdillon Road',
            'city' => 'Lagos',
            'state' => 'Lagos',
            'country' => 'NG',
            'timezone' => 'Africa/Lagos',
            'locale' => 'en-NG',
            'currency' => 'NGN',
            'date_format' => 'd/m/Y',
            'week_starts_on' => 1,
            'academic_year_start_month' => 9,
            'brand_color' => '#1d4ed8',
        ])->save();
        $settings->markReviewed();

        // Alpha has tweaked two modules away from the catalogue defaults; every
        // other module (and all of Beta) simply uses the default.
        SchoolModule::create(['module' => Module::Timetable->value, 'enabled' => true]);
        SchoolModule::create(['module' => Module::Fees->value, 'enabled' => false]);

        // Academic foundation — a current session with three terms, a handful of
        // levels + arms, and a starter subject list. All school-configured;
        // nothing here is baked into the code.
        $session = AcademicSession::create([
            'name' => '2025/2026',
            'starts_on' => '2025-09-01',
            'ends_on' => '2026-07-31',
        ]);
        $session->makeCurrent();

        foreach ([
            ['First Term', '2025-09-15', '2025-12-12', 1],
            ['Second Term', '2026-01-06', '2026-04-03', 2],
            ['Third Term', '2026-04-27', '2026-07-24', 3],
        ] as [$name, $from, $to, $pos]) {
            $period = $session->periods()->create([
                'name' => $name, 'starts_on' => $from, 'ends_on' => $to, 'position' => $pos,
            ]);

            if ($pos === 1) {
                $period->makeCurrent();
            }
        }

        $subjects = collect([
            'Mathematics' => 'MTH', 'English Language' => 'ENG', 'Basic Science' => 'BSC',
            'Social Studies' => 'SOS', 'Computer Studies' => 'CMP', 'Civic Education' => 'CIV',
        ])->map(fn ($code, $name) => Subject::create([
            'name' => $name, 'code' => $code, 'position' => 0,
        ]));

        $levels = collect();
        foreach ([
            ['Primary 1', 'PRI1', 1], ['Primary 2', 'PRI2', 2], ['Primary 3', 'PRI3', 3],
            ['JSS 1', 'JSS1', 4], ['JSS 2', 'JSS2', 5],
        ] as [$name, $code, $pos]) {
            $level = AcademicLevel::create(['name' => $name, 'code' => $code, 'position' => $pos]);

            $level->arms()->create(['name' => 'Gold', 'code' => 'G', 'position' => 1]);
            $level->arms()->create(['name' => 'Silver', 'code' => 'S', 'position' => 2]);

            $level->subjects()->sync(
                $subjects->pluck('id')->mapWithKeys(fn ($id) => [$id => ['school_id' => $alpha->id]])->all()
            );

            $levels->push($level->load('arms'));
        }

        // A cohort of students for Alpha, each placed in the current session.
        $cohort = Student::factory()->count(18)->create()->each(function (Student $student, int $i) use ($levels, $session) {
            $level = $levels[$i % $levels->count()];
            $arm = $level->arms[$i % 2];

            $student->enrollments()->create([
                'academic_session_id' => $session->id,
                'academic_level_id' => $level->id,
                'level_arm_id' => $arm->id,
                'status' => EnrollmentStatus::Active->value,
                'started_on' => '2025-09-15',
            ]);
        });

        // Guardians — one primary contact per student for the first dozen, a
        // second guardian for a few, and one guardian shared across two siblings.
        $cohort->take(12)->each(function (Student $student, int $i) {
            $mother = Guardian::factory()->create([
                'last_name' => $student->last_name,
                'phone' => '+234 802 000 '.str_pad((string) (1000 + $i), 4, '0', STR_PAD_LEFT),
            ]);
            $student->guardianLinks()->create([
                'guardian_id' => $mother->id,
                'relationship' => GuardianRelationship::Mother->value,
                'is_primary' => true,
            ]);

            if ($i % 3 === 0) {
                $father = Guardian::factory()->create(['last_name' => $student->last_name]);
                $student->guardianLinks()->create([
                    'guardian_id' => $father->id,
                    'relationship' => GuardianRelationship::Father->value,
                    'is_primary' => false,
                ]);
            }
        });

        // Siblings sharing a guardian.
        $sharedGuardian = Guardian::factory()->create(['last_name' => 'Ade', 'first_name' => 'Folake']);
        foreach ($cohort->slice(12, 2) as $sibling) {
            $sibling->guardianLinks()->create([
                'guardian_id' => $sharedGuardian->id,
                'relationship' => GuardianRelationship::LegalGuardian->value,
                'is_primary' => true,
            ]);
        }

        // Teaching staff for Alpha. One is linked to the Tomiwa Teacher account
        // (a teacher record is a professional record first — the link is optional
        // and does not itself create a login), one has resigned (history kept).
        $subjectList = $subjects->values();

        $tomiwaTeacher = Teacher::factory()->create([
            'first_name' => 'Tomiwa', 'last_name' => 'Adeyemi', 'employee_number' => 'EMP-1001',
            'email' => 'teacher@example.com',
        ]);
        $tomiwaTeacher->user_id = $tomiwa->id;
        $tomiwaTeacher->save();

        $others = Teacher::factory()->count(4)->create();
        $resigned = $others->last();
        $resigned->status = TeacherStatus::Resigned;
        $resigned->save();

        $staff = collect([$tomiwaTeacher])->concat($others->take(3));
        $staff->each(function (Teacher $teacher, int $i) use ($levels, $session, $subjectList) {
            $level = $levels[$i % $levels->count()];

            $teacher->assignments()->create([
                'academic_session_id' => $session->id,
                'academic_level_id' => $level->id,
                'level_arm_id' => $level->arms->first()->id,
                'subject_id' => $subjectList[$i % $subjectList->count()]->id,
                'started_on' => '2025-09-15',
            ]);

            // A prior-year assignment kept as history.
            $teacher->assignments()->create([
                'academic_session_id' => $session->id,
                'academic_level_id' => $levels[($i + 1) % $levels->count()]->id,
                'subject_id' => $subjectList[($i + 1) % $subjectList->count()]->id,
                'status' => 'ended',
                'started_on' => '2024-09-15',
                'ended_on' => '2025-07-24',
            ]);
        });

        // A published timetable for the current session's first term. Every
        // lesson lines up with an active teacher assignment and nothing clashes.
        $firstTerm = $session->periods()->orderBy('position')->first();
        $staffList = $staff->values();

        $timetable = Timetable::create([
            'academic_session_id' => $session->id,
            'academic_period_id' => $firstTerm?->id,
            'name' => 'First Term 2025/26',
        ]);

        foreach ([
            // [staffIndex, weekday(Mon=1), start, end, room]
            [0, 1, '08:00', '09:00', 'Room 1'],
            [1, 1, '09:00', '10:00', 'Room 2'],
            [2, 2, '08:00', '09:00', 'Lab'],
            [3, 2, '09:00', '10:00', 'Room 4'],
            [0, 3, '08:00', '09:00', 'Room 1'],
            [1, 3, '09:00', '10:00', 'Room 2'],
            [2, 4, '10:00', '11:00', 'Lab'],
            [3, 5, '08:00', '09:00', 'Room 4'],
        ] as [$i, $weekday, $start, $end, $room]) {
            $level = $levels[$i % $levels->count()];

            $timetable->entries()->create([
                'academic_level_id' => $level->id,
                'level_arm_id' => $level->arms->first()->id,
                'subject_id' => $subjectList[$i % $subjectList->count()]->id,
                'teacher_id' => $staffList[$i]->id,
                'weekday' => $weekday,
                'start_time' => $start,
                'end_time' => $end,
                'room' => $room,
            ]);
        }

        $timetable->publish();

        // Attendance — a bigger Primary 1 Gold cohort, one submitted register
        // (mixed statuses) and one open draft. Works independently of the
        // timetable module.
        $p1 = $levels[0];
        $p1Gold = $p1->arms->firstWhere('name', 'Gold');
        $p2 = $levels[1];
        $p2Gold = $p2->arms->firstWhere('name', 'Gold');

        Student::factory()->count(6)->create()->each(fn (Student $s) => $s->enrollments()->create([
            'academic_session_id' => $session->id,
            'academic_level_id' => $p1->id,
            'level_arm_id' => $p1Gold->id,
            'status' => EnrollmentStatus::Active->value,
            'started_on' => '2025-09-15',
        ]));
        Student::factory()->count(4)->create()->each(fn (Student $s) => $s->enrollments()->create([
            'academic_session_id' => $session->id,
            'academic_level_id' => $p2->id,
            'level_arm_id' => $p2Gold->id,
            'status' => EnrollmentStatus::Active->value,
            'started_on' => '2025-09-15',
        ]));

        $submitted = AttendanceRegister::create([
            'academic_session_id' => $session->id,
            'academic_period_id' => $firstTerm?->id,
            'academic_level_id' => $p1->id,
            'level_arm_id' => $p1Gold->id,
            'attendance_date' => '2025-09-16',
        ]);
        $statuses = [AttendanceStatus::Present, AttendanceStatus::Present, AttendanceStatus::Absent, AttendanceStatus::Late, AttendanceStatus::Excused];
        $submitted->eligibleStudents()->get()->each(function (Student $student, int $i) use ($submitted, $statuses) {
            $status = $statuses[$i % count($statuses)];
            $submitted->records()->create([
                'student_id' => $student->id,
                'status' => $status->value,
                'note' => $status === AttendanceStatus::Excused ? 'Medical appointment' : null,
            ]);
        });
        $submitted->submit($admin);

        $draft = AttendanceRegister::create([
            'academic_session_id' => $session->id,
            'academic_period_id' => $firstTerm?->id,
            'academic_level_id' => $p2->id,
            'level_arm_id' => $p2Gold->id,
            'attendance_date' => '2025-09-17',
        ]);
        $draft->eligibleStudents()->get()->each(fn (Student $student) => $draft->records()->create([
            'student_id' => $student->id,
        ]));

        // Assessments & assignments — configurable categories, a spread of
        // assessments for Primary 1 Gold / Mathematics (one draft, one published,
        // one locked) with mixed entered/unentered scores, plus assignments.
        $categories = collect([
            ['Classwork', 'CW', 1], ['Homework', 'HW', 2], ['Test', 'TEST', 3], ['Examination', 'EXAM', 4],
        ])->map(fn ($c) => AssessmentCategory::create([
            'name' => $c[0], 'code' => $c[1], 'position' => $c[2],
        ]));

        $maths = $subjects->firstWhere('code', 'MTH');
        $mathsContext = [
            'academic_session_id' => $session->id,
            'academic_period_id' => $firstTerm?->id,
            'academic_level_id' => $p1->id,
            'level_arm_id' => $p1Gold->id,
            'subject_id' => $maths->id,
        ];

        $lockedAssessment = Assessment::create([
            ...$mathsContext,
            'assessment_category_id' => $categories[0]->id,
            'title' => 'Week 2 Classwork — Counting',
            'assessment_date' => '2025-09-19',
            'max_score' => 10,
        ]);
        $lockedAssessment->created_by = $admin->id;
        $lockedAssessment->save();
        $lockedAssessment->eligibleStudents()->get()->each(function (Student $student, int $i) use ($lockedAssessment) {
            $lockedAssessment->scores()->create([
                'student_id' => $student->id,
                'score' => [8, 6, 9, 7, 10, 5][$i % 6],
            ]);
        });
        $lockedAssessment->publish();
        $lockedAssessment->lock($admin);

        $publishedAssessment = Assessment::create([
            ...$mathsContext,
            'assessment_category_id' => $categories[2]->id,
            'title' => 'First Term Test — Numbers',
            'assessment_date' => '2025-10-24',
            'max_score' => 20,
            'instructions' => 'Sections A and B. Show your working.',
        ]);
        $publishedAssessment->created_by = $admin->id;
        $publishedAssessment->save();
        $publishedAssessment->eligibleStudents()->get()->each(function (Student $student, int $i) use ($publishedAssessment) {
            // Roughly half the class scored so far.
            if ($i % 2 === 0) {
                $publishedAssessment->scores()->create(['student_id' => $student->id, 'score' => [15, 18, 12, 9][$i % 4]]);
            } else {
                $publishedAssessment->scores()->create(['student_id' => $student->id]);
            }
        });
        $publishedAssessment->publish();

        $draftAssessment = Assessment::create([
            ...$mathsContext,
            'assessment_category_id' => $categories[1]->id,
            'title' => 'Homework — Shapes',
            'assessment_date' => '2025-11-07',
            'max_score' => 5,
        ]);
        $draftAssessment->created_by = $admin->id;
        $draftAssessment->save();
        $draftAssessment->eligibleStudents()->get()->each(fn (Student $student) => $draftAssessment->scores()->create([
            'student_id' => $student->id,
        ]));

        // A published assignment with mixed completion, and a draft one.
        $publishedAssignment = Assignment::create([
            'academic_session_id' => $session->id,
            'academic_period_id' => $firstTerm?->id,
            'academic_level_id' => $p1->id,
            'level_arm_id' => $p1Gold->id,
            'subject_id' => $maths->id,
            'title' => 'Fractions worksheet',
            'instructions' => 'Complete questions 1–10 in the workbook.',
            'assigned_on' => '2025-09-22',
            'due_on' => '2025-09-26',
            'max_score' => 10,
        ]);
        $publishedAssignment->created_by = $admin->id;
        $publishedAssignment->teacher_id = $tomiwaTeacher->id;
        $publishedAssignment->save();
        $submissionStatuses = [
            AssignmentSubmissionStatus::Submitted, AssignmentSubmissionStatus::Submitted,
            AssignmentSubmissionStatus::Late, AssignmentSubmissionStatus::Pending, AssignmentSubmissionStatus::Exempt,
        ];
        $publishedAssignment->eligibleStudents()->get()->each(function (Student $student, int $i) use ($publishedAssignment, $submissionStatuses) {
            $status = $submissionStatuses[$i % count($submissionStatuses)];
            $publishedAssignment->submissions()->create([
                'student_id' => $student->id,
                'status' => $status->value,
                'submitted_on' => $status->isTurnedIn() ? '2025-09-25' : null,
            ]);
        });
        $publishedAssignment->publish();

        $draftAssignment = Assignment::create([
            'academic_session_id' => $session->id,
            'academic_period_id' => $firstTerm?->id,
            'academic_level_id' => $p1->id,
            'level_arm_id' => $p1Gold->id,
            'subject_id' => $maths->id,
            'title' => 'Reading log — Week 6',
            'assigned_on' => '2025-10-06',
            'due_on' => '2025-10-10',
        ]);
        $draftAssignment->created_by = $admin->id;
        $draftAssignment->teacher_id = $tomiwaTeacher->id;
        $draftAssignment->save();
        $draftAssignment->eligibleStudents()->get()->each(fn (Student $student) => $draftAssignment->submissions()->create([
            'student_id' => $student->id,
        ]));

        // One graduated student with a completed placement — history is kept.
        $alumnus = Student::factory()->status(StudentStatus::Graduated)->create(['first_name' => 'Ada', 'last_name' => 'Obi']);
        $alumnus->enrollments()->create([
            'academic_session_id' => $session->id,
            'academic_level_id' => $levels->last()->id,
            'level_arm_id' => $levels->last()->arms->first()->id,
            'status' => EnrollmentStatus::Completed->value,
            'started_on' => '2024-09-15',
            'ended_on' => '2025-07-24',
        ]);

        $tenant->forget();
    }
}
