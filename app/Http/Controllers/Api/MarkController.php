<?php

namespace App\Http\Controllers\Api;

use App\ClassSectionYear;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use DB;
use Auth;
use App\Mark;

class MarkController extends Controller
{
    public function index(Request $request)
    {
        $conds = [];
        $user = $request->user();

        if ($user->isInRole('student')) {
            array_push($conds, ['student_id', $user->id]);
        } elseif (!$user->isInRole(['admin', 'teacher', 'editor'])) {
            return response()->json(["status" => "Unauthorized"], 403);
        }
        if ($user->isInRole(['teacher'])) {
            $conds[] = ['subject_teacher.teacher_id', $user->id];
        }
        if ($request->input('stid')) {
            array_push($conds, ['subject_teacher.id', $request->input('stid')]);
        }
        if ($request->input('csyid')) {
            array_push($conds, ['subject_teacher.class_section_year_id', $request->input('csyid')]);
        }
        if ($request->input('tid')) {
            array_push($conds, ['term_id', $request->input('tid')]);
        }
        if ($request->input('csytid')) {
            array_push($conds, ['class_section_year_term_id', $request->input('csytid')]);
        }
        if ($request->input('srid')) {
            array_push($conds, ['student_roll_id', $request->input('srid')]);
        }

        $mark = Mark::join('subject_teacher_student', 'subject_teacher_student_id', 'subject_teacher_student.id')
            ->join('student_roll', 'student_roll_id', 'student_roll.id')
            ->join('students', 'student_id', 'students.id')
            ->join('users', 'students.id', 'users.id')
            ->join('subject_teacher', 'subject_teacher_id', 'subject_teacher.id')
            ->join('class_section_year_term', 'class_section_year_term_id', 'class_section_year_term.id')
            ->join('terms', 'term_id', 'terms.id')
            ->join('subject_teacher_portion', 'subject_teacher_portion_id', 'subject_teacher_portion.id')
            ->join('portions', 'portion_id', 'portions.id')
            ->join('subjects', 'subject_id', 'subjects.id')
            ->where($conds)
            ->select('marks.*', 'subject_id', 'subject', 'portion_id', 'portion', 'term_id', 'term', 'first_name', 'last_name', 'roll')
            ->orderByRaw('subject, portion, roll')
            ->get();

        return response()->json($mark);
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();
        $mark = Mark::join('subject_teacher_portion', 'subject_teacher_portion_id', 'subject_teacher_portion.id')
            ->where('marks.id', $id)
            ->select('marks.*', 'subject_teacher_id')
            ->first();
        $isAuthorized = DB::table('mark_auth')
                ->where('subject_teacher_id', $mark->subject_teacher_id)
                ->where('editor_id', $user->id)
                ->count() > 0;
        $isTeacher = DB::table('subject_teacher')
                ->where('teacher_id', $user->id)->count() > 0;
        if ($user->isInRole(['admin', 'editor']) || $isAuthorized || $isTeacher) {
            if ($request->input('mark')) {
                $mark->mark = $request->input('mark');
                if (!strcmp($mark->mark, 'A')) $mark->mark = -1;
                else if (!strcmp($mark->mark, 'N/A') || !strcmp($mark->mark, 'N')) $mark->mark = -2;
            }
            $mark->save();
            return response()->json($mark);
        }
        return response()->json(["status" => "Unauthorized"], 403);
    }

    public function tabulation(Request $request)
    {
        if (Auth::user()->isInRole(['admin', 'teacher', 'editor'])) {
            $csy = $request->input('csyid');
//            $res = DB::table('subject_teacher_student')
//                ->leftJoin('marks', function ($join) {
//                    $join->on('subject_teacher_student.id', 'subject_teacher_student_id')
//                        ->where('marks.mark', '>=', 0);
//                })
//                //->leftJoin('marks', 'subject_teacher_student.id', 'subject_teacher_student_id')
//                ->join('class_section_year_term', 'class_section_year_term.id', 'class_section_year_term_id')
//                ->join('terms', 'terms.id', 'term_id')
//                ->join('subject_teacher_portion', 'subject_teacher_portion.id', 'subject_teacher_portion_id')
//                ->join('portions', 'portions.id', 'portion_id')
//                ->join('subject_teacher', 'subject_teacher_student.subject_teacher_id', 'subject_teacher.id')
//                ->join('subjects', 'subjects.id', 'subject_id')
//                //->join('subject_teacher_student', 'subject_teacher_student.id', 'subject_teacher_student_id')
//                ->join('student_roll', 'student_roll_id', 'student_roll.id')
//                ->join('students', 'student_id', 'students.id')
//                ->join('users', 'students.id', 'users.id')
//                ->where('subject_teacher.class_section_year_id', $csy)
//                //->where('marks.mark', '>=', 0)
//                ->select(DB::raw('term, subject_code, subject, ( (sum(marks.mark) / sum(subject_teacher_portion.percentage) ) * avg(subjects.mark)) as mark, avg(subjects.mark) as total_mark, roll, first_name, last_name, student_id, term_id, class_section_year_term.percentage as term_percentage, subject_teacher_student.is_compulsory'))
//                ->groupBy(DB::raw('term, subject_code, subject, roll, first_name, last_name, student_id, term_id, class_section_year_term.percentage, subject_teacher_student.is_compulsory'))
//                ->orderByRaw('term_id, subject_code, roll')->get();
            $res = DB::select("select T1.id, T3.term, T3.subject_code, T3.subject, T2.mark, T3.total_mark, T3.roll, T3.first_name, T3.last_name, T3.student_id, T3.term_id, T3.term_percentage, T3.is_compulsory
FROM
`students` as T1
INNER JOIN
(select `students`.`id` AS ID, term, subject_code, subject, avg(subjects.mark) as total_mark, roll, first_name, last_name, student_id, term_id, class_section_year_term.percentage as term_percentage, subject_teacher_student.is_compulsory
from `subject_teacher_student`
left join `marks` on `subject_teacher_student`.`id` = `subject_teacher_student_id`
inner join `class_section_year_term` on `class_section_year_term`.`id` = `class_section_year_term_id`
inner join `terms` on `terms`.`id` = `term_id`
inner join `subject_teacher_portion` on `subject_teacher_portion`.`id` = `subject_teacher_portion_id`
inner join `portions` on `portions`.`id` = `portion_id`
inner join `subject_teacher` on `subject_teacher_student`.`subject_teacher_id` = `subject_teacher`.`id`
inner join `subjects` on `subjects`.`id` = `subject_id`
inner join `student_roll` on `student_roll_id` = `student_roll`.`id`
inner join `students` on `student_id` = `students`.`id`
inner join `users` on `students`.`id` = `users`.`id`
where `subject_teacher`.`class_section_year_id` = $csy
group by `students`.`id`, term, subject_code, subject, roll, first_name, last_name, student_id, term_id, class_section_year_term.percentage, subject_teacher_student.is_compulsory
order by term_id, subject_code, roll
) AS T3
ON T1.id = T3.ID
LEFT JOIN
(select term, subject_code, subject, ( (sum(marks.mark) / sum(subject_teacher_portion.percentage) ) * avg(subjects.mark)) as mark, avg(subjects.mark) as total_mark, roll, first_name, last_name, student_id, term_id, class_section_year_term.percentage as term_percentage, subject_teacher_student.is_compulsory
from `subject_teacher_student`
left join `marks` on `subject_teacher_student`.`id` = `subject_teacher_student_id` and `marks`.`mark` >= 0
inner join `class_section_year_term` on `class_section_year_term`.`id` = `class_section_year_term_id`
inner join `terms` on `terms`.`id` = `term_id`
inner join `subject_teacher_portion` on `subject_teacher_portion`.`id` = `subject_teacher_portion_id`
inner join `portions` on `portions`.`id` = `portion_id`
inner join `subject_teacher` on `subject_teacher_student`.`subject_teacher_id` = `subject_teacher`.`id`
inner join `subjects` on `subjects`.`id` = `subject_id`
inner join `student_roll` on `student_roll_id` = `student_roll`.`id`
inner join `students` on `student_id` = `students`.`id`
inner join `users` on `students`.`id` = `users`.`id`
where `subject_teacher`.`class_section_year_id` = $csy
group by term, subject_code, subject, roll, first_name, last_name, student_id, term_id, term_percentage, is_compulsory
order by term_id, subject_code, roll
) as T2
on T3.`id` = T2.student_id and T3.term_id = T2.term_id AND T2.subject_code = T3.subject_code");

            return response()->json($res);
        }

        return response()->json(["status" => "Unauthorized"], 403);
    }

    public function tabulation2(Request $request)
    {
        $year = $request->year;
        $class = $request->class;
        $section = $request->section;

        if ($year && $class && $section) {
            $csy = ClassSectionYear::where('year_id', $year)
                ->where('class_id', $class)
                ->where('section_id', $section)
                ->first();
            return view('common.tabulation2')->with('classSectionYear', $csy);
        }

        return view('common.tabulation2');
    }
}
