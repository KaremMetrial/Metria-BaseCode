<?php

declare(strict_types=1);

namespace Modules\Shared\Presentation\Http\Controllers;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/**
 * Generic index/show/store/update/destroy for a single Eloquent model,
 * behind the same Policy + ApiResponses envelope every other endpoint uses.
 *
 * A new plain-CRUD module (no workflow, no cross-aggregate rules) extends
 * this and declares four things instead of hand-writing five actions:
 *
 *   class WidgetController extends BaseCrudController
 *   {
 *       protected string $modelClass = Widget::class;
 *       protected string $resourceClass = WidgetResource::class;
 *       protected string $storeRequestClass = StoreWidgetRequest::class;
 *       protected ?string $updateRequestClass = UpdateWidgetRequest::class; // defaults to storeRequestClass
 *   }
 *
 * A module whose create/update/delete carries real business logic (approval
 * gates, DTOs, side-effecting Actions — see RBAC's RoleController or
 * Payment's refund flow) should keep hand-writing its controller: this base
 * is for the shape that's otherwise copy-pasted, not a mandate to route
 * every mutation through bare Eloquent::update().
 *
 * Authorization always goes through Gate + the model's Policy (viewAny/view/
 * create/update/delete abilities) — this class never inspects a user's role
 * directly, so it behaves identically for super-admin, admin, a tenant-scoped
 * "client" role, or any role added later; only the Policy and the seeded
 * permissions decide who can do what.
 */
abstract class BaseCrudController extends ApiController
{
    /** @var class-string<Model> */
    protected string $modelClass;

    /** @var class-string<JsonResource> */
    protected string $resourceClass;

    /** @var class-string<FormRequest> */
    protected string $storeRequestClass;

    /** @var class-string<FormRequest>|null defaults to storeRequestClass when null */
    protected ?string $updateRequestClass = null;

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', $this->modelClass);

        $resourceClass = $this->resourceClass;

        return $this->respond($resourceClass::collection($this->paginate($this->indexQuery())));
    }

    public function show(string $id): JsonResponse
    {
        $model = $this->findOrFail($id);

        Gate::authorize('view', $model);

        $resourceClass = $this->resourceClass;

        return $this->respond(new $resourceClass($model));
    }

    public function store(): JsonResponse
    {
        Gate::authorize('create', $this->modelClass);

        // Resolved (not type-hinted) so the concrete FormRequest subclass is
        // configurable per controller while still auto-validating: the
        // container runs ValidatesWhenResolved on any FormRequest it builds,
        // whether that happens via a type-hint or an explicit app() call.
        $request = app($this->storeRequestClass);

        $modelClass = $this->modelClass;
        $resourceClass = $this->resourceClass;

        $model = $modelClass::query()->create($this->storeAttributes($request));

        return $this->respondCreated(new $resourceClass($model));
    }

    public function update(string $id): JsonResponse
    {
        $model = $this->findOrFail($id);

        Gate::authorize('update', $model);

        $request = app($this->updateRequestClass ?? $this->storeRequestClass);

        $model->update($this->updateAttributes($request));

        $resourceClass = $this->resourceClass;

        return $this->respond(new $resourceClass($model->refresh()));
    }

    public function destroy(string $id): JsonResponse
    {
        $model = $this->findOrFail($id);

        Gate::authorize('delete', $model);

        $model->delete();

        return $this->respondNoContent();
    }

    protected function findOrFail(string $id): Model
    {
        $modelClass = $this->modelClass;

        return $modelClass::query()->findOrFail($id);
    }

    /** Override to add eager-loads, ordering, or tenant/user scoping. */
    protected function indexQuery(): Builder
    {
        $modelClass = $this->modelClass;

        return $modelClass::query()->latest();
    }

    protected function paginate(Builder $query): LengthAwarePaginator
    {
        $configPerPage = config('core.api.per_page', 20);
        $configMaxPerPage = config('core.api.max_per_page', 100);

        $perPage = min(
            is_numeric($configPerPage) ? (int) $configPerPage : 20,
            is_numeric($configMaxPerPage) ? (int) $configMaxPerPage : 100,
        );

        return $query->paginate($perPage);
    }

    /** Override to drop fields, inject the authenticated user/tenant id, hash secrets, etc. */
    protected function storeAttributes(FormRequest $request): array
    {
        return $request->validated();
    }

    protected function updateAttributes(FormRequest $request): array
    {
        return $request->validated();
    }
}
