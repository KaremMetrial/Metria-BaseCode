<?php

declare(strict_types=1);

namespace Modules\Shared\Presentation\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller;
use Modules\Shared\Presentation\Traits\ApiResponses;

abstract class ApiController extends Controller
{
    use ApiResponses;
    use AuthorizesRequests;
    use ValidatesRequests;
}
