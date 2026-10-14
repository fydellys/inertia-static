<?php

namespace App\Models;

use Fydellys\InertiaStatic\Concerns\HasStaticTags;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasStaticTags;

    protected $fillable = ['slug', 'title', 'body'];

    /**
     * @return array<int, string>
     */
    public function staticTags(): array
    {
        return ['posts', "post:{$this->id}"];
    }
}
