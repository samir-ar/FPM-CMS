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
        $normalized = $this->normalizeJpegEncoding($input);

        if(env('FORCE_S3_STORAGE', config('app.env') != 'local')){
            $filePath = Storage::disk('s3')->put(config('app.aws_bucket_project_name') . '/' . 'storage/' . $dir . '/' . $fileName, $normalized ?? file_get_contents($input));
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

            if ($normalized !== null) {
                file_put_contents($filePath.'/'.$fileName, $normalized);
            } else {
                move_uploaded_file($input, $filePath.'/'.$fileName);
            }
        }
		return $fileName;
	}

    /**
     * Progressive JPEGs frequently render as a blank image in the Flutter
     * app (a known Flutter/Skia decoder limitation, esp. on Android) even
     * though the file itself is perfectly valid and loads fine everywhere
     * else. Re-encoding through Intervention/GD always writes baseline
     * JPEG (GD's imagejpeg() has never supported writing progressive), so
     * this transparently normalizes every JPEG upload without changing
     * behavior for non-JPEG files (PDFs, Excel imports, PNGs, etc.).
     */
    private function normalizeJpegEncoding($input)
    {
        $mime = method_exists($input, 'getMimeType') ? $input->getMimeType() : null;
        if (!in_array($mime, ['image/jpeg', 'image/jpg'])) {
            return null;
        }

        try {
            return (string) Image::make($input->getRealPath())->encode('jpg', 90);
        } catch (\Throwable $e) {
            return null;
        }
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
