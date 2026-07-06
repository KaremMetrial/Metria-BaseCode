<?php

declare(strict_types=1);

namespace App\Core\Http\Controllers;

use App\Core\Traits\ApiResponses;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller;

abstract class ApiController extends Controller
{
    use ApiResponses;
    use AuthorizesRequests;
    use ValidatesRequests;
}
