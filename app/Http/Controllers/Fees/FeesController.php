<?php

namespace App\Http\Controllers\Fees;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Fees landing page: links to the fee screens the user may open.
 */
class FeesController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Fees/Index');
    }
}
