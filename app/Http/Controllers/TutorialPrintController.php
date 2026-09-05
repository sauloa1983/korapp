<?php

namespace App\Http\Controllers;

use App\Support\TutorialGuide;
use Illuminate\View\View;

class TutorialPrintController extends Controller
{
    public function __invoke(): View
    {
        return view('tutorial.print', [
            'lessons' => TutorialGuide::lessons(),
            'links' => TutorialGuide::links(),
            'showLinks' => false,
        ]);
    }
}
