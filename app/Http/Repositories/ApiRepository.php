<?php


namespace App\Http\Repositories;

use App\Favorite;
use App\Placeholder;
use App\TalkToUs;
use DB;
use DOMXPath;
use App\Poll;
use App\News;
use App\Memo;
use App\Link;
use App\Polls;
use App\Event;
use DOMDocument;
use App\Content;
use Carbon\Carbon;
use App\Volunteer;
use App\PollOption;
use App\AppUserPoll;
use App\Representative;
use App\Http\Traits\FileTrait;
use Storage;

class ApiRepository
{

    use FileTrait;

    public function getMemosByGroups($group_ids)
    {
        return Memo::whereHas('groups', function($q) use ($group_ids){
            return $q->whereIn('group_id', $group_ids)->orWhere('group_id', 81)->orWhere('group_id', 82);
        })->orderBy('created_at', 'desc');
    }

    public function getMemoFiles($memo_id)
    {
        $memo = Memo::find($memo_id);
        $files = $memo->files->map(function($m){
            return [
                'file' => public_path($m->src),
            ];
        })->pluck('file')->toArray();


        $path = 'tmp/'.uniqid().str_replace(' ', '_', $memo->name).'.zip';

        $zip = new \ZipArchive();
        if ($zip->open(public_path($path), \ZipArchive::CREATE) === true) {
            foreach ($files as $file) {
                $zip->addFile($file, basename($file));
            }
            $zip->close();
        }

        return $path;
    }

    public function getNewsByGroups($groups_ids)
    {
        return News::whereHas('groups', function($q) use ($groups_ids){
            return $q->whereIn('group_id', $groups_ids)->orWhere('group_id', 81)->orWhere('group_id', 82);
        })->orderBy('date', 'desc');
    }

    public function skipModeNews()
    {
        $url = "https://www.tayyar.org/Rss/Type/4";

        $xml = simplexml_load_file($url);

        //dd($xml);

        $items = [];

        $placeholder = Placeholder::where('type', 'news')->first()->image;

        foreach($xml->channel->item as $item) {
            preg_match('/(<img[^>]+>)/i', (string)$item->description, $matches);

            if(isset($matches[0])){
                //remove the match from the details
                $details = str_replace($matches[0],"",(string)$item->description);


                $img = $matches[0];
                $xpath = new DOMXPath(@DOMDocument::loadHTML($img));
                $file = $xpath->evaluate("string(//img/@src)");

                if(substr($details, 0, 12) == "<br /><br />")
                    $details = substr($details, 12);

            }

            else{
                $details = (string)$item->description;
                $file = null;
            }


            $items[] = (object)[
                'id' => null,
                'title' => (string)$item->title,
                'source' => null,
                'source_image' => $placeholder,
                'details' => $details,
                'link' => (string)$item->link,
                'file' => $file,
                'type' => 'image',
                'date' => strtotime(Carbon::parse((string)$item->children('http://www.w3.org/2005/Atom')->updated)->format('Y-m-d H:i:s')),
                'likes_nb' => null,
                'like' => null,
                'shares' => null,
            ];
        }

        return $items;
    }

    public function getPollsByGroups($user, $groups_ids)
    {
        $answered_polls = $user->polls->pluck('id')->toArray();
        return Poll::with('options')->whereNotIn('polls.id', $answered_polls)->whereHas('groups', function($q) use ($groups_ids){
           return $q->whereIn('group_id', $groups_ids)->orWhere('group_id', 81)->orWhere('group_id', 82);
        })->where('show',true)->get()->map(function($r){
            return [
                'question' => $r->question,
                'details' => $r->details,
                'options' => $r->options()->get()->map(function($r){
                    return [
                        'id' => $r->id,
                        'option' => $r->option
                    ];
                }),
                'strict_lang' => $r->strict_lang,

            ];
        });
    }

    public function getMessages($user)
    {
        return $user->notifications()->orderBy('created_at', 'desc')->get();
    }

    public function likeNews($user, $news_id, $status)
    {

        if($status == true){
            if(!$user->news->where('news_id', $news_id)->first())
                $user->news()->attach($news_id);
        }

        else{
            $user->news()->detach($news_id);

        }

        $n = News::find($news_id);

            $placeholder = Placeholder::where('type', 'news')->first()->image;
                return [
                    'id' => $n->id,
                    'title' => $n->title,
                    'source' => $n->source,
                    'source_image' => $n->source_image ? Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'images/news/' . $n->source_image) : Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'images/placeholders/' . $placeholder),
                    'details' => $n->details,
                    'type' => $n->type,
                    'file' => Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'images/news/attachments/' . $n->file),
                    'date' => strtotime($n->date),
                    'link' => null,
                    'likes_nb' => $n->users()->count(),
                    'like' => $n->users()->where('user_id', $user->id)->first() ? true : false,
                    'shares' => $n->shares,
                ];

    }

    public function makeFavorite($user, $member_id, $make_favorite)
    {


        if(!$user->favorites()->where('favorite', $member_id)->first() && $make_favorite == true){

            $favorite = new Favorite();
            $favorite->user_id = $user->id;
            $favorite->favorite = $member_id;
            $favorite->save();
        }
        elseif($user->favorites()->where('favorite', $member_id)->first() && $make_favorite == false){
            $favorite = $user->favorites()->where('favorite', $member_id)->first();
            $favorite->delete();
        }

        return ;

    }

    public function shareNews($news_id, $user)
    {
        $n = News::find($news_id);
        $n->shares = $n->shares + 1;
        $n->save();

            $placeholder = Placeholder::where('type', 'news')->first()->image;

            return [
                'id' => $n->id,
                'title' => $n->title,
                'source' => $n->source,
                'source_image' => $n->source_image ? Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'images/news/' . $n->source_image) : Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'images/placeholders/' . $placeholder),
                'details' => $n->details,
                'type' => $n->type,
                'file' => Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'images/news/attachments/' . $n->file),
                'date' => strtotime($n->date),
                'link' => Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'images/news/attachments/' . $n->file),
                'likes_nb' => $n->users()->count(),
                'like' => $n->users()->where('user_id', $user->id)->first() ? true : false,
                'shares' => $n->shares,
            ];
    }

    public function getPreviousPolls($user, $groups_ids)
    {
        //return polls answerd with the answered option flagged
        return $user->polls()->whereHas('groups', function($q) use ($groups_ids){
            return $q->whereIn('group_id', $groups_ids)->orWhere('group_id', 81)->orWhere('group_id', 82);
        })->get()->map(function($p) use ($user){

            return [
                'question' => $p->question,
                'date' => $p->created_at->toDateString(),
                'answer' => $this->getPollOptionsWithRatings($user->id, $p->id),
            ];
        });
    }

    public function getMembersByGroup($groups_ids)
    {
        return Poll::where('show', true)->whereHas('groups', function($q) use ($groups_ids){
            return $q->whereIn('group_id', $groups_ids)->orWhere('group_id', 81)->orWhere('group_id', 82);
        })->get();
    }



    public function userAnswerPoll($user, $option_id)
    {
        $option = PollOption::find($option_id);
        $poll_id = $option->poll_id;

        //remove old records
        $user->polls()->detach($poll_id);

        //add the record
        $user->polls()->attach($poll_id, ['option_id' => $option_id]);

        return true;
    }

    public function getPollOptionsWithRatings($user_id, $poll_id)
    {
        $total_count = AppUserPoll::where('poll_id', $poll_id)->count();

        return Poll::find($poll_id)->options()->get()->map(function($o) use ($total_count, $user_id){

            return [
                'id' => $o->id,
                'percentage' => ($o->answers->count() * 100) / $total_count,
                'option' => $o->option,
                'answered' => $o->answers->where('app_user_id',$user_id)->first() ? true : false,
            ];
        });
    }

    public function getEventsPreviousByGroup($groups_ids)
    {
        return Event::whereHas('groups', function($q) use ($groups_ids){
            return $q->whereIn('group_id', $groups_ids)->orWhere('group_id', 81)->orWhere('group_id', 82);
        })->where('to_date', '<', Carbon::today())->get();
    }

    public function getEventsUpcomingByGroup($groups_ids)
    {
        return Event::whereHas('groups', function($q) use ($groups_ids){
            return $q->whereIn('group_id', $groups_ids)->orWhere('group_id', 81)->orWhere('group_id', 82);
        })->where('to_date', '>', Carbon::today())->get();
    }

    public function saveVolunteer($user, $volunteer_id)
    {
        $user->volunteers()->attach($volunteer_id);

        return;
    }

    public function getPublicLinks()
    {
        $links = Link::orderBy('order', 'asc')
            ->orderBy('created_at', 'desc')
            ->where('public', true)->get();

        return $links;
    }

    public function userTalkToUs($user, $data)
    {
        $talk = new TalkToUs();
        $talk->user_id = $user->id;
        $talk->title = $data['title'];
        $talk->text = $data['message'];

        $talk->save();

        return ['message' => 'Thank you for you message.'];
    }

    public function getLinksByGroups($groups_ids)
    {
        $links = Link::whereHas('groups', function($q) use ($groups_ids){
           return $q->whereIn('group_id', $groups_ids)->orWhere('group_id', 81)->orWhere('group_id', 82);
        })->orderBy('order', 'asc')->orderBy('created_at', 'desc')->get();

        return $links;
    }

    public function getNewsInstance($n, $user, $placeholder)
    {

        return [
            'id' => $n->id,
            'title' => $n->title,
            'source' => $n->source,
            'source_image' => $n->source_image ? Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'images/news/' . $n->source_image) : Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'images/placeholders/' . $placeholder),
            'details' => $n->details,
            'type' => $n->type,
            'file' => ($n->file)?Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'images/news/attachments/' . $n->file):null,
            'thumbnail' => $n->thumbnail ? Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'images/news/' . $n->thumbnail) : Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'images/placeholders/' . $placeholder),
            'date' => strtotime($n->date),
            'link' => ($n->file)?Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'images/news/attachments/' . $n->file):null,
            'likes_nb' => $n->users()->count(),
            'like' => $n->users()->where('user_id', $user->id)->first() ? true : false,
            'shares' => $n->shares,
            'force_lang' => $n->strict_lang,
        ];
    }


    public function getEventInstance($e)
    {
        $name = ($e->strict_lang == "en")?$e->getTranslation('name','en'):$e->getTranslation('name','ar');
        $details = ($e->strict_lang == "en")?$e->getTranslation('details','en'):$e->getTranslation('details','ar');
        return [
            'id' => $e->id,
            'name' => $name,
            'details' => $details,
            'organized_by' => $e->organized_by,
            'location' => $e->location,
            'lng' => floatval($e->lng),
            'lat' => floatval($e->lat),
            'from_date' => strtotime($e->from_date),
            'to_date' => strtotime($e->to_date),
            'thumbnail' => Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'images/events/' . $e->image),
            'images' => $e->images->map(function($i){
                return [
                    'img' => Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'images/events/' . $i->src),
                ];
            })->pluck('img')->toArray(),
        ];
    }

}
