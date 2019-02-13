<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\Resource;

class Category extends Resource
{
    public function toArray($request)
    {
        $parent = parent::toArray($request);
        // ambil data books yang berelasi dengan category menggunakan pivot
        $data['books'] = $this->books()->paginate(6);
        $data          = array_merge($parent, $data);
        return [
            'status'  => 'success',
            'message' => 'category data',
            'data'    => $data,
        ];
    }
}
