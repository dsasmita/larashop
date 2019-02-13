<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\Resource;

class Book extends Resource
{
    public function toArray($request)
    {
        $parent             = parent::toArray($request);
        $data['categories'] = $this->categories;
        $data               = array_merge($parent, $data);
        return [
            'status'  => 'success',
            'message' => 'book data',
            'data'    => $data,
        ];
    }
}
