<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Abstracts\BaseRepository;
use App\Core\Abstracts\DataTransferObject;
use App\Core\Contracts\CurrencyRegistryResolver;
use App\Core\Contracts\RepositoryInterface;
use App\Domain\Currency\Services\CurrencyRegistryResolverImpl;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Territory\Models\Country;
use App\Domain\Territory\Repositories\CountryRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DummyDto extends DataTransferObject
{
    public function __construct(
        public readonly string $name,
        public readonly PaymentStatus $status,
        public readonly ?DummyNestedDto $nested = null
    ) {}
}

class DummyNestedDto extends DataTransferObject
{
    public function __construct(public readonly string $code) {}
}

class CoreAbstractsAndContractsTest extends TestCase
{
    use RefreshDatabase;

    public function test_data_transfer_object_normalizes_enums_and_nested_dtos(): void
    {
        $nested = new DummyNestedDto('US');
        $dto = new DummyDto('Test Order', PaymentStatus::Succeeded, $nested);

        $array = $dto->toArray();

        $this->assertSame('Test Order', $array['name']);
        $this->assertSame('succeeded', $array['status']);
        $this->assertIsArray($array['nested']);
        $this->assertSame('US', $array['nested']['code']);

        $json = json_encode($dto);
        $this->assertJsonStringEqualsJsonString(
            json_encode(['name' => 'Test Order', 'status' => 'succeeded', 'nested' => ['code' => 'US']]),
            $json
        );
    }

    public function test_currency_registry_resolver_contract_is_bound_and_resolves(): void
    {
        $resolver = app(CurrencyRegistryResolver::class);

        $this->assertInstanceOf(CurrencyRegistryResolverImpl::class, $resolver);
        $this->assertSame(2, $resolver->minorUnitsFor('USD'));
        $this->assertSame(3, $resolver->minorUnitsFor('BHD'));
        $this->assertSame(0, $resolver->minorUnitsFor('JPY'));
    }

    public function test_base_repository_implements_interface_and_scopes_queries_by_tenant(): void
    {
        DB::table('tenants')->insert([
            ['id' => 'tenant-a', 'name' => 'Tenant A', 'slug' => 'tenant-a', 'active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 'tenant-b', 'name' => 'Tenant B', 'slug' => 'tenant-b', 'active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 'tenant-c', 'name' => 'Tenant C', 'slug' => 'tenant-c', 'active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $repository = app(CountryRepository::class);

        $this->assertInstanceOf(BaseRepository::class, $repository);
        $this->assertInstanceOf(RepositoryInterface::class, $repository);

        Country::query()->create([
            'tenant_id' => 'tenant-a',
            'name' => ['en' => 'Country A'],
            'iso_code_2' => 'CA',
            'iso_code_3' => 'CAA',
            'phone_code' => '+11',
            'currency' => 'USD',
            'is_active' => true,
        ]);

        Country::query()->create([
            'tenant_id' => 'tenant-b',
            'name' => ['en' => 'Country B'],
            'iso_code_2' => 'CB',
            'iso_code_3' => 'CBB',
            'phone_code' => '+22',
            'currency' => 'EUR',
            'is_active' => true,
        ]);

        $tenantAQuery = $repository->query('tenant-a');
        $this->assertCount(1, $tenantAQuery->get());
        $this->assertSame('CA', $tenantAQuery->first()->iso_code_2);

        $tenantBQuery = $repository->query('tenant-b');
        $this->assertCount(1, $tenantBQuery->get());
        $this->assertSame('CB', $tenantBQuery->first()->iso_code_2);

        $created = $repository->create([
            'name' => ['en' => 'Country C'],
            'iso_code_2' => 'CC',
            'iso_code_3' => 'CCC',
            'phone_code' => '+33',
            'currency' => 'GBP',
            'is_active' => true,
        ], 'tenant-c');

        $this->assertSame('tenant-c', $created->tenant_id);
    }
}
