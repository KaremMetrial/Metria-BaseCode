#!/usr/bin/env bash
set -euo pipefail

service_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
root_dir="$(cd "$service_dir/../.." && pwd)"
project="metrial-realtime-cluster"
env_file="$root_dir/compose/realtime-cluster.env"
base_file="$root_dir/compose/docker-compose.yml"
cluster_file="$root_dir/compose/docker-compose.realtime-cluster.yml"
compose=(docker compose -p "$project" --env-file "$env_file" -f "$base_file" -f "$cluster_file")
artifacts_dir="$root_dir/artifacts/realtime-cluster"
step="initializing"

log_step() {
  step="$1"
  echo "[cluster-test] $step"
}

on_error() {
  local status=$?
  echo "[cluster-test] FAILED step=$step line=$1 status=$status command=$2" >&2
}
trap 'on_error "$LINENO" "$BASH_COMMAND"' ERR

cleanup() {
  local status=$?
  trap - EXIT
  echo "[cluster-test] 15 cleanup (status=$status)"
  # Retain one self-consistent evidence set for every gate run. In particular,
  # a successful run must not leave diagnostics from an earlier failure behind.
  mkdir -p "$artifacts_dir"
  node -e 'process.stdout.write(JSON.stringify({ exit_code: Number(process.argv[1]), last_step: process.argv[2] }) + "\n")' "$status" "$step" > "$artifacts_dir/wrapper-status.json"
  "${compose[@]}" logs --no-color > "$artifacts_dir/docker.log" || true
  "${compose[@]}" ps > "$artifacts_dir/compose-status.log" || true
  "${compose[@]}" down -v --remove-orphans || true
  exit "$status"
}
trap cleanup EXIT

wait_for_ready() {
  local url=$1
  for _ in $(seq 1 60); do
    if curl --fail --silent "$url/health/ready" >/dev/null; then return 0; fi
    sleep 1
  done
  echo "Timed out waiting for $url" >&2
  return 1
}

log_step "01 validating tools"
command -v docker >/dev/null
command -v curl >/dev/null
command -v node >/dev/null
log_step "02 rendering Compose"
"${compose[@]}" config --quiet
log_step "03 starting infrastructure"
"${compose[@]}" up -d --build --quiet-build mysql redis app internal-nginx queue realtime-a realtime-b
log_step "08 waiting for Node A"
wait_for_ready http://127.0.0.1:6101
log_step "09 waiting for Node B"
wait_for_ready http://127.0.0.1:6102
log_step "05 running migrations"
"${compose[@]}" exec -T app php artisan migrate:fresh --force

log_step "10 creating fixtures"
mkdir -p "$artifacts_dir"
fixture_stdout="$artifacts_dir/fixtures.json"
fixture_stderr="$artifacts_dir/fixtures.stderr.log"
"${compose[@]}" exec -T app php artisan tinker --execute='
$make = function (string $slug, string $email) {
  $tenantId = (string) \Illuminate\Support\Str::uuid();
  \Illuminate\Support\Facades\DB::table("tenants")->insert(["id" => $tenantId, "name" => $slug, "slug" => $slug, "active" => true, "created_at" => now(), "updated_at" => now()]);
  $user = \Modules\Auth\Domain\Models\User::create(["tenant_id" => $tenantId, "name" => $slug, "email" => $email, "password" => "cluster-test-password"]);
  $wallet = \Modules\Wallet\Domain\Models\Wallet::create(["tenant_id" => $tenantId, "user_id" => $user->id, "balance" => 1000, "held" => 0, "currency" => "USD"]);
  return compact("tenantId", "user", "wallet");
};
$a1 = $make("cluster-tenant-a", "cluster-a1@example.test");
$a2 = $make("cluster-tenant-a-user-2", "cluster-a2@example.test");
\Illuminate\Support\Facades\DB::table("users")->where("id", $a2["user"]->id)->update(["tenant_id" => $a1["tenantId"]]);
\Illuminate\Support\Facades\DB::table("wallets")->where("id", $a2["wallet"]->id)->update(["tenant_id" => $a1["tenantId"]]);
$b1 = $make("cluster-tenant-b", "cluster-b1@example.test");
$tokenA1 = $a1["user"]->createToken("cluster-a1-one")->plainTextToken;
$tokenA1Second = $a1["user"]->createToken("cluster-a1-two")->plainTextToken;
$conversation = app(\Modules\Shared\Infrastructure\Tenancy\TenantManager::class)->runInContext($a1["tenantId"], function () use ($a1, $a2) {
  return app(\Modules\Communication\Domain\Services\CommunicationService::class)->createConversation(
    $a1["user"],
    \Modules\Communication\Domain\Enums\ConversationType::Direct,
    null,
    [(string) $a2["user"]->id],
  );
});
$walletView = \Spatie\Permission\Models\Permission::findOrCreate("wallets.view", "web");
setPermissionsTeamId($a1["tenantId"]);
$a2["user"]->fresh()->givePermissionTo($walletView);
$tokenA2 = $a2["user"]->fresh()->createToken("cluster-a2")->plainTextToken;
$tokenB1 = $b1["user"]->createToken("cluster-b1")->plainTextToken;
echo json_encode(["tenant_a" => $a1["tenantId"], "tenant_b" => $b1["tenantId"], "conversation" => $conversation->id, "a1" => ["id" => $a1["user"]->id, "email" => $a1["user"]->email, "token" => $tokenA1, "token_id" => explode("|", $tokenA1, 2)[0], "second_token" => $tokenA1Second, "second_token_id" => explode("|", $tokenA1Second, 2)[0], "wallet" => $a1["wallet"]->id], "a2" => ["id" => $a2["user"]->id, "email" => $a2["user"]->email, "token" => $tokenA2], "b1" => ["id" => $b1["user"]->id, "email" => $b1["user"]->email, "token" => $tokenB1, "wallet" => $b1["wallet"]->id]]);
' > "$fixture_stdout" 2> "$fixture_stderr"
fixtures="$(< "$fixture_stdout")"

log_step "11 validating fixtures"
node -e '
const fixture = JSON.parse(process.argv[1]);
for (const path of [["tenant_a"], ["tenant_b"], ["conversation"], ["a1", "id"], ["a1", "token"], ["a1", "second_token"], ["a2", "id"], ["a2", "token"], ["b1", "id"], ["b1", "token"], ["a1", "wallet"]]) {
  let value = fixture;
  for (const key of path) value = value?.[key];
  if (typeof value !== "string" || value.trim() === "") throw new Error(`missing fixture ${path.join(".")}`);
}
' "$fixtures"
export REALTIME_CLUSTER_FIXTURES="$fixtures"
export REALTIME_CLUSTER_ROOT="$root_dir"
export REALTIME_CLUSTER_PROJECT="$project"
export REALTIME_CLUSTER_ENV_FILE="$env_file"
export REALTIME_CLUSTER_COMPOSE_FILE="$cluster_file"
log_step "13 running Vitest"
vitest_output="$artifacts_dir/vitest.log"
set +e
npx vitest run tests/integration/two-node-delivery.test.ts --reporter=verbose --no-file-parallelism 2>&1 | tee "$vitest_output"
vitest_status=${PIPESTATUS[0]}
set -e
if [ "$vitest_status" -ne 0 ]; then
  echo "[cluster-test] Vitest failed with status=$vitest_status; diagnostics retained in $artifacts_dir" >&2
  exit "$vitest_status"
fi
log_step "14 collecting results"
