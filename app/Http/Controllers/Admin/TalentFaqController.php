<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\V2\TalentFaq;
use Illuminate\Http\Request;

// FPM Talent > FAQ: questions and answers, one list per language (the app shows two language buttons, then the questions
// in bold; tapping a question opens its answer).
class TalentFaqController extends Controller
{
    public function index(Request $request)
    {
        $lang = $request->query('language') === 'en' ? 'en' : 'ar';

        return view('cms.talent.faqs')->with([
            'layout' => 'layouts.cms',
            'language' => $lang,
            'faqs' => TalentFaq::where('language', $lang)->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'language' => 'required|in:ar,en',
            'question' => 'required|string|max:1000',
            'answer' => 'required|string|max:10000',
        ]);
        TalentFaq::create($data + ['is_active' => true, 'sort_order' => (int) TalentFaq::where('language', $data['language'])->max('sort_order') + 1]);

        return redirect()->route('admin.talent-faqs.index', ['language' => $data['language']])->with('message', 'Question added.');
    }

    public function update(Request $request)
    {
        $request->validate(['faqs.*.question' => 'required|string|max:1000', 'faqs.*.answer' => 'required|string|max:10000', 'faqs.*.sort_order' => 'nullable|integer|min:0|max:9999']);

        $lang = 'ar';
        foreach ((array) $request->input('faqs', []) as $id => $in) {
            $faq = TalentFaq::find($id);
            if (!$faq) {
                continue;
            }
            $lang = $faq->language;
            if (!empty($in['delete'])) {
                $faq->delete();
                continue;
            }
            $faq->update([
                'question' => $in['question'],
                'answer' => $in['answer'],
                'sort_order' => (int) ($in['sort_order'] ?? $faq->sort_order),
                'is_active' => !empty($in['is_active']),
            ]);
        }

        return redirect()->route('admin.talent-faqs.index', ['language' => $request->input('language', $lang)])->with('message', 'FAQ saved.');
    }
}
