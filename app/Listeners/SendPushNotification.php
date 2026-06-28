<?php

namespace App\Listeners;

use DB;
use Log;
use Exception;
use App\AppUser;
use App\Notification;
use App\Events\NewItem;
use App\Http\Traits\FileTrait;
use Illuminate\Queue\InteractsWithQueue;
use App\Http\Traits\PushNotificationTrait;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Http\Repositories\FpmApisRepository;

class SendPushNotification
{
    use PushNotificationTrait;
    use FileTrait;



    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  NewItem  $event
     * @return void
     */
    public function handle(NewItem $event)
    {

        DB::beginTransaction();

        try {
            $fpmRepo = new FpmApisRepository();

            $data = $event->request->all();

            $add_data = [];

            $groups_data = [
                'group_ids' => implode(',', $data['groups']),
            ];

            $groupMembers = $fpmRepo->getCMSGroupsMembers($groups_data);

            $member_ids = [];

            foreach ($groupMembers as $member) {
                $member_ids[] = $member->MemberId;
            }

            $users = AppUser::whereIn('member_id', $member_ids)->get();

            // $player_ids = array_filter($users->pluck('player_id')->toArray());
            $player_ids = array_unique(array_filter($users->pluck('player_id')->toArray()));

            //dd($player_ids);
            if (empty($player_ids))
                return;


            $content_en = str_limit(strip_tags($data['text']), 240);
            $content_ar = isset($data['text_ar']) ? str_limit(strip_tags($data['text_ar']), 240) : '';

            //WORK AROUND replace the &quot; (double quote) and &#39; (single quote) with '
            $content_en = str_replace("&quot;", "'", $content_en);
            $content_ar = str_replace("&quot;", "'", $content_ar);

            $content_en = str_replace("&amp;", "&", $content_en);
            $content_ar = str_replace("&amp;", "&", $content_ar);

            $content_en = str_replace("&#39;", "'", $content_en);
            $content_ar = str_replace("&#39;", "'", $content_ar);

            $content_en = str_replace("&lrm;", "", $content_en);
            $content_ar = str_replace("&lrm;", "", $content_ar);

            $content_en = html_entity_decode($content_en);
            $content_ar = html_entity_decode($content_ar);

            $player_ids = array_values($player_ids);

            if (env('TEST_SERVER')) {
                $player_ids = ['07c9cf0e-8051-11ec-9940-4ef164ba95fd'];
            }

            $info = [
                'headings' => [
                    'en' => $data['title'],
                    'ar' => isset($data['title_ar']) ? str_limit($data['title_ar'], 65) : '',
                ],
                'contents' => [
                    'en' => remove_special_characters($content_en),
                    'ar' => remove_special_characters($content_ar),
                ],


                //'player_ids' => ['07c9cf0e-8051-11ec-9940-4ef164ba95fd'], //Player Id Mouhamed Mouneer She3rani
                'player_ids' => $player_ids,
                //'player_ids' => ['4b3b0dd9-54d7-4d60-a721-b91098a42f92'] saleh
                //'player_ids' => ['ece00e94-f350-4c2d-a45e-0af0bf874e03','288bc8ab-2072-46d1-b0e0-406560793d99','4b3b0dd9-54d7-4d60-a721-b91098a42f92']
            ];

            if (isset($data['image_path'])) {
                $info['image'] = $data['image_path'];
            }


            $add_data['news'] = isset($data['news']) ? $data['news'] : null;
            $add_data['events'] = isset($data['event']) ? $data['event'] : null;
            $add_data['polls'] = isset($data['poll']) ? $data['poll'] : null;

            $signal_response = $this->oneSignal($info, $add_data);

            if (isset($signal_response['error'])) {
                throw new Exception('Push notification error: ' . $signal_response['error']);
            }

            //create new notification and sync to users
            $notification = new Notification();
            $notification->title = $data['title'];
            $notification->text = strip_tags($data['text']);
            $notification->save();

            $users->each(function ($u) use ($notification) {
                $u->notifications()->attach($notification->id);
            });

            DB::commit();
        } catch (Exception $exception) {

            DB::rollback();

            \Log::info($exception->getMessage());
        }
    }
}
