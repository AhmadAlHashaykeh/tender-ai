<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class PublicDocumentationController extends Controller
{
    public function __invoke(): View
    {
        return view('public.documentation');
    }
}
