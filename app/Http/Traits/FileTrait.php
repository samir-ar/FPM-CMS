<?php

namespace App\Http\Traits;

use Image;
use Storage;

trait FileTrait
{
	public function copyFile($input , $destination){
		$fileName = time().$this->slugify($input->getClientOriginalName());
		copy($input, "$destination/".$fileName);
		return $fileName;
	}


    public function slugify($word){
        // Arabic (or any non-ASCII) characters left in the on-disk filename break
        // move_uploaded_file() on Windows/WAMP ("Failed to open stream" — PHP mis-encodes
        // multi-byte UTF-8 paths via the ANSI codepage there). Strip to safe ASCII only;
        // the user-facing name is stored separately (e.g. title fields), not this filename.
        $slug = preg_replace('/[^a-zA-Z0-9.-]/', '-', $word);
        return preg_replace('/-+/', '-', $slug);
    }

	public function moveFile($input, $dir, $thumbFlag = null, $width = null, $height = null, $thumb_path = null)
	{
		$fileName = time().$this->slugify($input->getClientOriginalName());
        if(env('APP_ENV') != 'local'){
            $filePath = Storage::disk('s3')->put(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . $dir . '/' . $fileName, file_get_contents($input));
        }else{
            $filePath = public_path( $dir . '/');

            //save the thumb before moving the image
            if($thumbFlag){
                $thumbPath = public_path($thumb_path.'/');

                if($height)
                    $thumb = Image::make($input->getRealPath())
                        ->resize($width, $height)
                        ->save($thumbPath.'/'.$fileName);
                else
                    $thumb = Image::make($input->getRealPath())
                        ->resize($width, null, function($constraint){
                            $constraint->aspectRatio();
                        })
                ->save($thumbPath.'/'.$fileName);
            }

            move_uploaded_file($input, $filePath.'/'.$fileName);
        }
		return $fileName;
	}

	public function removeFile($file){
		$filePath = public_path($file);

		if(is_file($filePath)){
			unlink($filePath);
		}

		return;
	}

	public function removeFiles($dir)
	{
		$files = glob($dir . '/*');
		foreach ($files as $file){
			if(is_file($file)){
				unlink($file);
			}
		}
	}
}
?>
