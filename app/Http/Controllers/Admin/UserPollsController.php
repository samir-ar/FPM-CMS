<?php

namespace App\Http\Controllers\Admin;

use App\Poll;
use DataTables;
use App\PollOption;
use App\AppUserPoll;
use Illuminate\Http\Request;
use App\Http\Traits\FormTrait;
use App\Http\Traits\FileTrait;
use App\Http\Controllers\Controller;

class UserPollsController extends Controller
{
    use FormTrait;
    use FileTrait;

    public function index(Request $request)
    {
        $summary = null;
        if (request('poll_id')) {
            $pollId = request('poll_id');
            $totalVotes = AppUserPoll::where('poll_id', $pollId)->count();
            $options = PollOption::withTrashed()->where('poll_id', $pollId)->get();

            $summary = "<div style='margin-bottom:10px;text-align:center;'>" .
                "<span class='label label-primary' style='font-size:13px;margin-right:8px;'>Total Voters: {$totalVotes}</span>";

            $optionStats = $options->map(function ($option) use ($pollId, $totalVotes) {
                $count = AppUserPoll::where('poll_id', $pollId)->where('option_id', $option->id)->count();
                return [
                    'text' => e($option->getTranslation('option', 'ar')),
                    'count' => $count,
                    'percentage' => $totalVotes > 0 ? round(($count / $totalVotes) * 100) : 0,
                ];
            });

            // First two options use the app's exact colors: AppColors.primary
            // (0xFFF78E1E) and that same color at 35% alpha over white — the
            // identical pair used for the selected/unselected bars in the app's
            // own poll results view. Any further options fall back to a graded
            // scale between them so the badges never look identical.
            $fixedColors = ['#F78E1E', '#FCD7B0'];
            $darkRgb = [0xC1, 0x65, 0x0A];
            $lightRgb = [0xFC, 0xE7, 0xD5];
            $minPct = $optionStats->min('percentage');
            $maxPct = $optionStats->max('percentage');
            $textColor = '#7a4a10';

            foreach ($optionStats as $i => $stat) {
                if (isset($fixedColors[$i])) {
                    $bgColor = $fixedColors[$i];
                } else {
                    $t = $maxPct > $minPct
                        ? ($stat['percentage'] - $minPct) / ($maxPct - $minPct)
                        : 1;
                    $rgb = [
                        round($lightRgb[0] + ($darkRgb[0] - $lightRgb[0]) * $t),
                        round($lightRgb[1] + ($darkRgb[1] - $lightRgb[1]) * $t),
                        round($lightRgb[2] + ($darkRgb[2] - $lightRgb[2]) * $t),
                    ];
                    $bgColor = sprintf('#%02X%02X%02X', $rgb[0], $rgb[1], $rgb[2]);
                }

                $summary .= "<span class='label' style='font-size:13px;margin-right:8px;background-color:{$bgColor};color:{$textColor};'>{$stat['text']}: {$stat['count']} ({$stat['percentage']}%)</span>";
            }

            $summary .= "</div>";

            // The shared table_ajax layout wraps custom_btn1 in a `float:left`
            // div sized to its content, so text-align:center alone has no room
            // to center within — widen that wrapper to the full header instead.
            $summary .= "<script>document.currentScript.closest('div[style*=\"float:left\"]').style.cssText = 'width:100%;';</script>";
        }

        if($request->ajax()) {

            $data = AppUserPoll::where('poll_id', request('poll_id'));


            return DataTables::of($data)

                ->addColumn('poll', function($row){
                    return Poll::withTrashed()->find($row->poll_id)->question;
                })

                ->addColumn('option', function($row){
                    return PollOption::withTrashed()->find($row->option_id)->option;
                })

                ->addColumn('user', function($row){
                    if($row->user){
                        return $row->user->name.'-'.$row->user->phone_number;
                    }
                    return null;
                })
                ->rawColumns(['poll', 'option', 'user'])
                ->make(true);
        }


        return view('components.table_ajax')->with([
            'layout'    => 'layouts.cms',
            'pageTitle'	=> 'User Polls',
            'table_title' => '',
            'slug'		=> 'Poll',
            //'custom_btn' => "<a href='" . route('admin.userPolls.create') ."' class='btn btn-primary'></a>",
            'custom_btn1' => $summary,
            'headers'	=> ['id', 'User', 'Option', 'Poll', 'Created At'],
            'action' => route('admin.userPolls.index').'?poll_id='.request('poll_id'),
            'columns' => json_encode([
                ['data' => 'id', 'name' => 'id'],
                ['data' =>  'user', 'name'=> 'user'],
                ['data' =>  'option', 'name'=> 'option'],
                ['data' =>  'poll', 'name'=> 'poll'],
                ['data' =>  'created_at', 'name'=> 'created_at'],
            ]),

        ]);
    }
}
