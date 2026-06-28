<?php
namespace App\V2;

use Illuminate\Database\Eloquent\Model;
use App\Http\Traits\MyTranslationTrait;

class DynamicRepresentative extends Model
{
    use MyTranslationTrait;
    
    protected $fillable = ['title','text','order'];
    public $translatable = ['title','text'];
    
    public function persons(){
        return $this->hasMany(Person::class,"");
    }

    
    
}
