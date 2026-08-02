<?php

/*
|--------------------------------------------------------------------------
| API routes
|--------------------------------------------------------------------------
| Every business capability now owns its routes in
| modules/{Module}/Presentation/routes/api.php, self-registered via
| loadRoutesFrom() from that module's own Service Provider — see, e.g.,
| Modules\Auth\Infrastructure\Providers\AuthServiceProvider.
|
| This file still exists because bootstrap/app.php's withRouting(api: ...)
| is what establishes the `api` middleware group and the automatic `/api`
| URI prefix in the router in the first place — every module route file's
| own ->middleware(['api', ...]) depends on that group existing. Without
| this file registered here, that group is never created at all.
|
| Add routes here only for endpoints that don't belong to any one module.
*/
