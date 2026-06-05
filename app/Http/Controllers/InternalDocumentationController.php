<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class InternalDocumentationController extends Controller
{
    public function __invoke(): View
    {
        return view('internal.documentation');
    }
}
