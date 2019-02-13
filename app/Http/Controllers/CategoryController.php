<?php

namespace App\Http\Controllers;

use App\Category;
use App\Http\Resources\Categories as CategoryResourceCollection;
use App\Http\Resources\Category as CategoryResource;

class CategoryController extends Controller
{
    public function index()
    {
        $criteria = Category::paginate(6);
        return new CategoryResourceCollection($criteria);
    }

    public function random($count)
    {
        $criteria = Category::select('*')
            ->inRandomOrder()
            ->limit($count)
            ->get();
        return new CategoryResourceCollection($criteria);
    }

    public function slug($slug)
    {
        $criteria = Category::where('slug', $slug)->first();
        return new CategoryResource($criteria);
    }
}
