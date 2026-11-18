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
use App\V2\GuestPollVote;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UserPollsController extends Controller
{
    use FormTrait;
    use FileTrait;

    public function index(Request $request)
    {
        $summary = null;
        if (request('poll_id')) {
            $pollId = request('poll_id');
            $memberVotes = AppUserPoll::where('poll_id', $pollId)->count();
            $guestVotes = GuestPollVote::where('poll_id', $pollId)->count();
            $totalVotes = $memberVotes + $guestVotes;
            $options = PollOption::withTrashed()->where('poll_id', $pollId)->get();

            $summary = "<div style='margin-bottom:10px;text-align:center;'>" .
                "<span class='label label-primary' style='font-size:13px;margin-right:8px;'>Total Voters: {$totalVotes} (members {$memberVotes}, guests {$guestVotes})</span>";

            $optionStats = $options->map(function ($option) use ($pollId, $totalVotes) {
                $count = AppUserPoll::where('poll_id', $pollId)->where('option_id', $option->id)->count()
                    + GuestPollVote::where('poll_id', $pollId)->where('option_id', $option->id)->count();
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

            $pollId = request('poll_id');

            // Member answers and guest answers in ONE list. The member id comes from the account that answered (a
            // hidden/soft-deleted account still shows its member). hardware_id / device_model are only filled for
            // answers sent by the newer app builds, and the columns may not exist yet on an environment that has not
            // run the migrations - then they show as empty instead of failing.
            $col = fn($table, $alias, $column) => Schema::hasColumn($table, $column) ? "$alias.$column" : 'NULL';

            $members = DB::table('users_polls as up')
                ->leftJoin('app_users as u', 'u.id', '=', 'up.app_user_id')
                ->where('up.poll_id', $pollId)
                ->selectRaw("up.id AS id, 'Member' AS kind, u.member_id AS member_id, u.name AS name, u.phone_number AS phone, up.option_id AS option_id, "
                    . $col('users_polls', 'up', 'device_model') . " AS device_model, " . $col('users_polls', 'up', 'hardware_id') . " AS hardware_id, up.created_at AS created_at");

            $guests = DB::table('guest_poll_votes as g')
                ->where('g.poll_id', $pollId)
                ->selectRaw("g.id AS id, 'Guest' AS kind, NULL AS member_id, NULL AS name, NULL AS phone, g.option_id AS option_id, "
                    . $col('guest_poll_votes', 'g', 'device_model') . " AS device_model, " . $col('guest_poll_votes', 'g', 'hardware_id') . " AS hardware_id, g.created_at AS created_at");

            $data = DB::query()->fromSub($members->unionAll($guests), 'answers');

            $pollQuestion = Poll::withTrashed()->find($pollId)->question ?? '';
            $optionNames = PollOption::withTrashed()->where('poll_id', $pollId)->get()->mapWithKeys(fn($o) => [$o->id => $o->option])->all();

            return DataTables::of($data)
                ->addColumn('poll', fn($row) => $pollQuestion)
                ->addColumn('option', fn($row) => $optionNames[$row->option_id] ?? '')
                ->addColumn('user', fn($row) => $row->kind === 'Guest' ? 'Guest' : trim(($row->name ?? '') . ($row->phone ? '-' . $row->phone : '')))
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
            'headers'	=> ['id', 'Type', 'Member ID', 'User', 'Option', 'Poll', 'Device', 'Hardware ID', 'Created At'],
            'action' => route('admin.userPolls.index').'?poll_id='.request('poll_id'),
            'columns' => json_encode([
                ['data' => 'id', 'name' => 'id'],
                ['data' => 'kind', 'name' => 'kind'],
                ['data' => 'member_id', 'name' => 'member_id'],
                ['data' =>  'user', 'name'=> 'user', 'orderable' => false, 'searchable' => false],
                ['data' =>  'option', 'name'=> 'option', 'orderable' => false, 'searchable' => false],
                ['data' =>  'poll', 'name'=> 'poll', 'orderable' => false, 'searchable' => false],
                ['data' => 'device_model', 'name' => 'device_model'],
                ['data' => 'hardware_id', 'name' => 'hardware_id'],
                ['data' =>  'created_at', 'name'=> 'created_at'],
            ]),

        ]);
    }
}
