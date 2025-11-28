<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\View\View;

class StoresController extends Controller
{
    /**
     * Display the stores page.
     */
    public function index(): View
    {
        return view('stores.index');
    }
}
