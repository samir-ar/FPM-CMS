<?php


namespace App\Http\Repositories\V2;

use DB;
use GuzzleHttp\Client;
use App\SocialMediaPost;
use App\SocialMediaAttachement;

class FacebookRepository
{
    public $facebook_page_id;
    public $facebook_token;

    public $insta_user_id;
    public $insta_access_token;

    public function __construct()
    {
        $this->facebook_page_id = config('services.facebook.page_id');
        $this->facebook_token = config('services.facebook.token');
        $this->insta_user_id = config('services.instagram.user_id');
        $this->insta_access_token = config('services.instagram.token');
    }

    public function storeFacebookFeeds()
    {
        $client = new Client();
        $response = $client->get('https://graph.facebook.com/' . $this->facebook_page_id. '/posts'.'?fields=full_picture,picture,created_time,message,is_published,is_spherical,likes.summary(true),attachments&access_token='.$this->facebook_token);

        $result = json_decode($response->getBody()->getContents());

        DB::transaction(function() use ($result){

            foreach($result->data as $data){

                $post = new SocialMediaPost();
                $post->platform_type = 'facebook';
                $post->description = isset($data->message) ? $data->message : '';
                $post->likes = $data->likes->summary->total_count;
                $post->date = strtotime($data->created_time);

                $post->save();

                foreach($data->attachments->data as $attachment){
                    if(isset($attachment->media)){
                        $att = new SocialMediaAttachement();
                        $att->type = $attachment->type  == 'photo' ? 'image' : 'video';

                        $media = $attachment->media;


                        if(isset($media->source))
                            $att->video_url = $media->source;

                        $att->image_url = $media->image->src;

                        $post->attachements()->save($att);
                    }
                }

            }
        });
    }

    public function storeInstaFeeds()
    {
        $client = new Client();
        $response = $client->get("https://api.instagram.com/v1/users/self/media/recent/?access_token=".$this->insta_access_token);

        $result = json_decode($response->getBody()->getContents());


        DB::transaction(function() use ($result) {

            foreach($result->data as $data){
                $post = new SocialMediaPost();
                $post->platform_type = 'instagram';

                $post->description = $data->caption->text;
                $post->likes = $data->likes->count;
                $post->comments = $data->comments->count;
                $post->date = $data->created_time;

                $post->save();

                $attachement = new SocialMediaAttachement();

                $attachement->type = $data->type == 'video' ? $data->type : 'image';

                $attachement->video_url = $data->type == 'video' ? $data->videos->standard_resolution->url : '';
                $attachement->image_url = $data->images->thumbnail->url;

                $post->attachements()->save($attachement);
            }
        });

    }


    public function getSocialPosts()
    {
        return SocialMediaPost::orderBy('date')->with('attachements')->get();
    }
}