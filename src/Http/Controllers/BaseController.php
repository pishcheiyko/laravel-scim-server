<?php

namespace ArieTimmerman\Laravel\SCIMServer\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * Base class
 */
class BaseController extends Controller
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;
}
