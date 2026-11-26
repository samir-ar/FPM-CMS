<?php

namespace App\Http\Traits;

use Carbon\Carbon;
use App\V2\AppUser;
use App\V2\Notification;
use Illuminate\Support\Facades\Log;

trait BirthdayWishesTrait
{
    use PushNotificationTrait;

    /**
     * Sends a personalized "Happy Birthday" push + in-app notification to every
     * member whose date_of_birth matches today's month/day, who has used the
     * app since 24 Sept 2026 and hasn't already been wished this calendar year. Returns the number wished.
     */
    public function sendTodaysBirthdayWishes(?string $date = null): int
    {
        $today = $date ? Carbon::parse($date, config('app.timezone')) : Carbon::now(config('app.timezone'));

        $users = AppUser::whereNotNull('date_of_birth')
            ->whereMonth('date_of_birth', $today->month)
            ->whereDay('date_of_birth', $today->day)
            // only accounts in use: opened the app at least one day since activity tracking began (24 Sept 2026),
            // the same rule as CMS > App Users "since 24 Sept". Old, unused installations get no wish.
            ->whereExists(function ($q) {
                $q->selectRaw('1')->from('user_activity_logs')
                    ->whereColumn('user_activity_logs.app_user_id', 'app_users.id')
                    ->where('user_activity_logs.activity_date', '>=', \App\Exports\AppInstallationReportExport::TRACKING_START);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('last_birthday_wish_sent_at')
                    ->orWhereYear('last_birthday_wish_sent_at', '<>', $today->year);
            })
            ->get();

        $sentCount = 0;

        foreach ($users as $user) {
            try {
                $name = remove_special_characters($user->name);

                $headings = [
                    'en' => 'Happy Birthday 🎂',
                    'ar' => 'عيد ميلاد سعيد 🎂',
                ];

                $contents = [
                    'en' => remove_special_characters("Happy Birthday, {$name}! Best wishes from the Free Patriotic Movement family 🎉"),
                    'ar' => remove_special_characters("عيد ميلاد سعيد يا {$name}! كل عام وأنت من عائلة التيار الوطني الحر 🎉"),
                ];

                if (!empty($user->player_id)) {
                    $info = [
                        'headings' => $headings,
                        'contents' => $contents,
                        'player_ids' => [$user->player_id],
                    ];

                    $signal_response = $this->oneSignal($info, []);

                    if (isset($signal_response['error'])) {
                        Log::info('Birthday push error for user ' . $user->id . ': ' . $signal_response['error']);
                    }
                }

                $notification = new Notification();
                $notification->title = $headings['en'];
                $notification->text = $contents['en'];
                $notification->save();

                $user->notifications()->attach($notification->id);

                $user->last_birthday_wish_sent_at = $today->copy()->setTimezone('UTC');
                $user->save();

                $sentCount++;
            } catch (\Exception $exception) {
                Log::info('Failed to send birthday wish to user ' . $user->id . ': ' . $exception->getMessage());
            }
        }

        return $sentCount;
    }
}
