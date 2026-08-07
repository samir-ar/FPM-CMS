<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\V2\CompetencyVacancy;
use App\Http\Traits\FormTrait;
use App\Http\Controllers\Controller;
use DataTables;

class CompetencyVacanciesController extends Controller
{
    use FormTrait;

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = CompetencyVacancy::query()->orderByDesc('created_at');
            return DataTables::of($data)
                ->addColumn('status_badge', fn($row) => $row->is_active
                    ? "<span class='label label-success'>نشط</span>"
                    : "<span class='label label-default'>غير نشط</span>")
                ->addColumn('action', fn($row) =>
                    "<a href='" . route('admin.competency-vacancies.nominations', $row->id) . "' class='btn btn-xs btn-default' style='margin-right:4px'><i class='fa fa-users'></i> الترشيحات</a>" .
                    "<a href='" . route('admin.competency-vacancies.toggle-active', $row->id) . "' class='btn btn-xs " . ($row->is_active ? 'btn-warning' : 'btn-success') . "' style='margin-right:4px'>" .
                    ($row->is_active ? 'إيقاف' : 'تفعيل') . "</a>" .
                    "<a href='" . route('admin.competency-vacancies.edit', $row->id) . "' class='btn btn-xs btn-info' style='margin-right:4px'><i class='fa fa-edit'></i></a>" .
                    "<a data-toggle='modal' class='delete-link btn btn-xs btn-danger' href='#deleteModal' id='" .
                    route('admin.competency-vacancies.destroy', $row->id) . "'><i class='fa fa-trash'></i></a>")
                ->rawColumns(['status_badge', 'action'])
                ->make(true);
        }

        return view('components.table_ajax')->with([
            'layout'      => 'layouts.cms',
            'pageTitle'   => 'منصة الكفاءات — المناصب الشاغرة',
            'table_title' => '',
            'slug'        => 'competency-vacancies',
            'custom_btn'  =>
                "<a href='" . route('admin.competency-vacancies.create') . "' class='btn btn-primary'>إضافة منصب</a>",
            'headers'     => ['#', 'المسمى', 'تاريخ البدء', 'تاريخ الانتهاء', 'الحالة', 'Action'],
            'action'      => route('admin.competency-vacancies.index'),
            'columns'     => json_encode([
                ['data' => 'id',           'name' => 'id'],
                ['data' => 'title',        'name' => 'title'],
                ['data' => 'start_date',   'name' => 'start_date'],
                ['data' => 'end_date',     'name' => 'end_date'],
                ['data' => 'status_badge', 'name' => 'status_badge', 'searchable' => false, 'sortable' => false],
                ['data' => 'action',       'name' => 'action', 'searchable' => false, 'sortable' => false],
            ]),
        ]);
    }

    public function toggleActive($id)
    {
        $vacancy = CompetencyVacancy::findOrFail($id);
        $vacancy->update(['is_active' => !$vacancy->is_active]);

        return back()->with('message', $vacancy->is_active
            ? 'تم تفعيل المنصب بنجاح'
            : 'تم إيقاف عرض المنصب بنجاح');
    }

    public function create()
    {
        return view('components.form')->with([
            'layout'      => 'layouts.cms',
            'pageTitle'   => 'إضافة منصب شاغر',
            'method'      => 'post',
            'form_action' => route('admin.competency-vacancies.store'),
            'boxes' => [[
                'wrapper-class' => 'col-md-12',
                'class'         => 'box-default',
                'box-header'    => 'معلومات المنصب',
                'form_fields'   => [
                    $this->drawHtml('small_text', 'المسمى', 'title', null, null, '', 'col-md-12 required'),
                    $this->drawHtml('text', 'الوصف', 'description', null, null, '', 'col-md-12'),
                    $this->drawHtml('date-time-picker', 'تاريخ بدء الترشيح', 'start_date', null, null, '', 'col-md-6 required'),
                    $this->drawHtml('date-time-picker', 'تاريخ انتهاء الترشيح', 'end_date', null, null, '', 'col-md-6 required'),
                ],
            ]],
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title'      => 'required|string|max:191',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
        ]);

        CompetencyVacancy::create([
            'title'       => $request->title,
            'description' => $request->description ?: null,
            'start_date'  => $request->start_date,
            'end_date'    => $request->end_date,
        ]);

        return redirect()->route('admin.competency-vacancies.index')
            ->with('message', 'تمت إضافة المنصب بنجاح');
    }

    public function edit($id)
    {
        $vacancy = CompetencyVacancy::findOrFail($id);

        return view('components.form')->with([
            'layout'      => 'layouts.cms',
            'pageTitle'   => 'تعديل منصب شاغر',
            'method'      => 'update',
            'form_action' => route('admin.competency-vacancies.update', $id),
            'boxes' => [[
                'wrapper-class' => 'col-md-12',
                'class'         => 'box-default',
                'box-header'    => 'معلومات المنصب',
                'form_fields'   => [
                    $this->drawHtml('small_text', 'المسمى', 'title', $vacancy->title, null, '', 'col-md-12 required'),
                    $this->drawHtml('text', 'الوصف', 'description', $vacancy->description, null, '', 'col-md-12'),
                    $this->drawHtml('date-time-picker', 'تاريخ بدء الترشيح', 'start_date', $vacancy->start_date->format('Y-m-d H:i'), null, '', 'col-md-6 required'),
                    $this->drawHtml('date-time-picker', 'تاريخ انتهاء الترشيح', 'end_date', $vacancy->end_date->format('Y-m-d H:i'), null, '', 'col-md-6 required'),
                ],
            ]],
        ]);
    }

    public function update(Request $request, $id)
    {
        $vacancy = CompetencyVacancy::findOrFail($id);

        $request->validate([
            'title'      => 'required|string|max:191',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
        ]);

        $vacancy->update([
            'title'       => $request->title,
            'description' => $request->description ?: null,
            'start_date'  => $request->start_date,
            'end_date'    => $request->end_date,
        ]);

        return redirect()->route('admin.competency-vacancies.index')
            ->with('message', 'تم تعديل المنصب بنجاح');
    }

    public function destroy($id)
    {
        CompetencyVacancy::findOrFail($id)->delete();
        return back()->with('message', 'تم حذف المنصب بنجاح');
    }

    public function nominations(Request $request, $id)
    {
        $vacancy = CompetencyVacancy::findOrFail($id);

        if ($request->ajax()) {
            $data = $vacancy->nominations()->with('submittedBy')->orderByDesc('created_at');
            return DataTables::of($data)
                ->addColumn('nomination_type_ar', fn($row) => $row->nomination_type === 'self' ? 'ترشيح ذاتي' : 'ترشيح آخرين')
                ->addColumn('submitted_by_name', fn($row) => optional($row->submittedBy)->name)
                ->addColumn('action', fn($row) =>
                    "<a data-toggle='modal' class='delete-link btn btn-xs btn-danger' href='#deleteModal' id='" .
                    route('admin.competency-vacancies.nominations.destroy', [$vacancy->id, $row->id]) . "'><i class='fa fa-trash'></i></a>")
                ->rawColumns(['action'])
                ->make(true);
        }

        return view('components.table_ajax')->with([
            'layout'      => 'layouts.cms',
            'pageTitle'   => 'الترشيحات — ' . $vacancy->title,
            'table_title' => '',
            'slug'        => 'competency-vacancies-nominations-' . $vacancy->id,
            'custom_btn'  => "<a href='" . route('admin.competency-vacancies.index') . "' class='btn btn-default'>عودة الى المناصب</a>",
            'headers'     => ['#', 'النوع', 'الاسم الكامل', 'القضاء', 'البلدة', 'رقم الهاتف', 'مقدم الطلب', 'Action'],
            'action'      => route('admin.competency-vacancies.nominations', $vacancy->id),
            'columns'     => json_encode([
                ['data' => 'id',                 'name' => 'id'],
                ['data' => 'nomination_type_ar',  'name' => 'nomination_type', 'searchable' => false],
                ['data' => 'full_name',           'name' => 'full_name'],
                ['data' => 'district',            'name' => 'district'],
                ['data' => 'town',                'name' => 'town'],
                ['data' => 'phone',               'name' => 'phone'],
                ['data' => 'submitted_by_name',   'name' => 'submitted_by_name', 'searchable' => false, 'sortable' => false],
                ['data' => 'action',              'name' => 'action', 'searchable' => false, 'sortable' => false],
            ]),
        ]);
    }

    public function destroyNomination($vacancyId, $nominationId)
    {
        CompetencyVacancy::findOrFail($vacancyId)
            ->nominations()->findOrFail($nominationId)->delete();

        return back()->with('message', 'تم حذف الترشيح بنجاح');
    }
}
