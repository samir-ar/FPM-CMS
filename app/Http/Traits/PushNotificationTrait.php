<?php
namespace App\Http\Traits;
use App\V2\AppUser;
use DB;
use Illuminate\Support\Facades\Log;

trait PushNotificationTrait
{

    function oneSignal($info, $data = []){
        ini_set('memory_limit', '-1');
        ini_set('upload_max_filesize', '200M');
        ini_set('post_max_size', '200M');
        ini_set('client_max_body_size', '200M');
        ini_set('max_execution_time', 3600);

        if(isset($info['player_ids'])){
            $chunks = array_chunk($info['player_ids'], 200);

            foreach ($chunks as $chunk) {
                $contents = $info['contents'];
                $headings = $info['headings'];
                $included_segments = !isset($info['included_segments']) ? [] : $info['included_segments'];
                $excluded_segments = !isset($info['excluded_segments']) ? [] : $info['excluded_segments'];
                $result = [];
                //$data = [];

                $fields = array(
                    'app_id' => env('ONE_SIGNAL_APP_ID'),
                    //'included_segments' => $included_segments,
                    //'excluded_segments' => $excluded_segments,
                    'contents' => $contents,
                    'headings' => $headings,
                );

                //add data and text to the data
                $data['title']  = $headings['en'];
                $data['message'] = $contents['en'];


                // if(isset($info['player_ids']))
                    // $fields['include_player_ids'] = $info['player_ids'];
                $fields['include_player_ids'] = $chunk;

                if(isset($info['included_segments']))
                    $fields['included_segments'] = $info['included_segments'];

                if(isset($info['excluded_segments']))
                    $fields['excluded_segments'] = $info['excluded_segments'];

                if(isset($info['image'])){
                    //for android
                    $fields['big_picture'] = $info['image'];

                    list($width, $height, $type, $attr) = getimagesize($info['image']);
                    $data['image_url'] = $info['image'];
                    $data['image_width'] = $width;
                    $data['image_height'] = $height;


                    //for ios
                    $media_id = 'id'.str_random(5);
                    $fields['ios_attachments'] = [$media_id => $info['image']];

                }

                if(isset($info['ios_badgeType'])){
                    $fields['ios_badgeType'] = $info['ios_badgeType'];
                }
                if(isset($info['ios_badgeCount'])){
                    $fields['ios_badgeCount'] = $info['ios_badgeCount'];
                }
                if(isset($info['tags'])){
                    $fields['tags'] = $info['tags'];
                }


                if(!empty($data))
                    $fields['data'] = $data;


                $fields = json_encode($fields);

                try{
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, env('ONE_SIGNAL_URL'));
                    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json; charset=utf-8',
                        'Authorization: Basic '.env('ONE_SIGNAL_REST_API_KEY')));

                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
                    curl_setopt($ch, CURLOPT_HEADER, FALSE);
                    curl_setopt($ch, CURLOPT_POST, TRUE);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

                    $response = curl_exec($ch);
                    $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $err = curl_errno($ch);

                    curl_close($ch);
                    if ($err) {
                        $result['error'] = 'Curl Error: '.$err.' - Failed to Send Push';
                        Log::info('Curl Error: '.$err.' - Failed to Send Push');
                    } else {
                        $result = $this->parse_push_response($response);
                    }

                }catch (\Exception $ex){
                    $result['error'] = 'Exception Error: '.$ex->getMessage().' - Failed to Send Push';
                    Log::info('Exception Error: '.$ex->getMessage().' - Failed to Send Push');
                }
            }
        } else {
            $contents = $info['contents'];
            $headings = $info['headings'];
            $included_segments = !isset($info['included_segments']) ? [] : $info['included_segments'];
            $excluded_segments = !isset($info['excluded_segments']) ? [] : $info['excluded_segments'];
            $result = [];
            //$data = [];

            $fields = array(
                'app_id' => env('ONE_SIGNAL_APP_ID'),
                //'included_segments' => $included_segments,
                //'excluded_segments' => $excluded_segments,
                'contents' => $contents,
                'headings' => $headings,
            );

            //add data and text to the data
            $data['title']  = $headings['en'];
            $data['message'] = $contents['en'];


            if(isset($info['player_ids']))
                $fields['include_player_ids'] = $info['player_ids'];

            if(isset($info['included_segments']))
                $fields['included_segments'] = $info['included_segments'];

            if(isset($info['excluded_segments']))
                $fields['excluded_segments'] = $info['excluded_segments'];

            if(isset($info['image'])){
                //for android
                $fields['big_picture'] = $info['image'];

                list($width, $height, $type, $attr) = getimagesize($info['image']);
                $data['image_url'] = $info['image'];
                $data['image_width'] = $width;
                $data['image_height'] = $height;


                //for ios
                $media_id = 'id'.str_random(5);
                $fields['ios_attachments'] = [$media_id => $info['image']];

            }

            if(isset($info['ios_badgeType'])){
                $fields['ios_badgeType'] = $info['ios_badgeType'];
            }
            if(isset($info['ios_badgeCount'])){
                $fields['ios_badgeCount'] = $info['ios_badgeCount'];
            }
            if(isset($info['tags'])){
                $fields['tags'] = $info['tags'];
            }


            if(!empty($data))
                $fields['data'] = $data;


            $fields = json_encode($fields);

            try{
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, env('ONE_SIGNAL_URL'));
                curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json; charset=utf-8',
                    'Authorization: Basic '.env('ONE_SIGNAL_REST_API_KEY')));

                curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
                curl_setopt($ch, CURLOPT_HEADER, FALSE);
                curl_setopt($ch, CURLOPT_POST, TRUE);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

                $response = curl_exec($ch);
                $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $err = curl_errno($ch);

                curl_close($ch);
                if ($err) {
                    $result['error'] = 'Curl Error: '.$err.' - Failed to Send Push';
                    Log::info('Curl Error: '.$err.' - Failed to Send Push');
                } else {
                    $result = $this->parse_push_response($response);
                }

            }catch (\Exception $ex){
                $result['error'] = 'Exception Error: '.$ex->getMessage().' - Failed to Send Push';
                Log::info('Exception Error: '.$ex->getMessage().' - Failed to Send Push');
            }
        }


        $responseData = json_decode($response, true);
        if (isset($responseData['errors']['invalid_player_ids'])) {
            $invalidPlayerIds = $responseData['errors']['invalid_player_ids'];
            foreach ($invalidPlayerIds as $playerId) {
                Log::info($playerId);
                $user = AppUser::where('player_id', $playerId)->first();
                $user->player_id = null;
                $user->save();
            }

            $result['error'] = 'Invalid Player IDs Found, Failed to Send Push. Please try again.';
        }

        return $result;
    }

    public function parse_push_response($response){
        $response = json_decode($response,true);
        $response = (array) $response;
        $data = [];
        $data['status'] = '';
        $data['message'] = '';
        $data['debugger'] = '';
        if(isset($response['id'])){
            if(!empty($response['id'])){//200 ok
                $data['status'] = 'OK';
                $data['message'] = 'Push Sent Successfully to all devices';
                $data['debugger'] = 'Push Sent Successfully to all devices';
            }else{//200 no subscribed players
                $data['status'] = 'OK_WITH_ERRORS';
                if(isset($response['errors'])){
                    $data['message'] = isset($response['errors'][0]) ? $response['errors'][0] : 'All included players are not subscribed';
                    $data['debugger'] = isset($response['errors'][0]) ? $response['errors'][0] : 'All included players are not subscribed';
                }
            }
        }else{

            if(isset($response['errors'])){
                if(!empty($response['errors']['invalid_player_ids'])){//200 invalid player ids
                    $data['status'] = 'OK_WITH_ERRORS';
                    $data['message'] = 'Some Users are Invalid';
                    $data['debugger'] ='Some Users are Invalid';
                }else{//400
                    $data = [];
                    $data['error'] = isset($response['errors'][0]) ? $response['errors'][0] : 'Invalid Notification Content';
                }
            }else{//400
                $data = [];
                $data['error'] = 'Unknown Error';
            }
        }
        return $data;
    }

    public function is_valid_player_id($player_id)
    {
        //validate the format
        $chunks = explode('-',$player_id);

        if(count($chunks) != 5 || strlen($chunks[0]) != 8 || strlen($chunks[1]) != 4 || strlen($chunks[2]) != 4 || strlen($chunks[3]) != 4 || strlen($chunks[4]) != 12){
            return false;
        }

        return true;
    }

}
