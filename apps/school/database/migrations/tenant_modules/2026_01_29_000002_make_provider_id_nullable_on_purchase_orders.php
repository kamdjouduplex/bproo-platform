<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::connection('tenant')->getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::connection('tenant')->statement('ALTER TABLE purchase_orders DROP CONSTRAINT IF EXISTS purchase_orders_provider_id_foreign');
            DB::connection('tenant')->statement('ALTER TABLE purchase_orders ALTER COLUMN provider_id DROP NOT NULL');
            DB::connection('tenant')->statement('ALTER TABLE purchase_orders ADD CONSTRAINT purchase_orders_provider_id_foreign FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE');
        } else {
            // MySQL
            DB::connection('tenant')->statement('ALTER TABLE purchase_orders DROP FOREIGN KEY purchase_orders_provider_id_foreign');
            DB::connection('tenant')->statement('ALTER TABLE purchase_orders MODIFY provider_id BIGINT UNSIGNED NULL');
            DB::connection('tenant')->statement('ALTER TABLE purchase_orders ADD CONSTRAINT purchase_orders_provider_id_foreign FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE');
        }
    }

    public function down(): void
    {
        $driver = Schema::connection('tenant')->getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::connection('tenant')->statement('ALTER TABLE purchase_orders DROP CONSTRAINT IF EXISTS purchase_orders_provider_id_foreign');
            DB::connection('tenant')->statement('ALTER TABLE purchase_orders ALTER COLUMN provider_id SET NOT NULL');
            DB::connection('tenant')->statement('ALTER TABLE purchase_orders ADD CONSTRAINT purchase_orders_provider_id_foreign FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE');
        } else {
            DB::connection('tenant')->statement('ALTER TABLE purchase_orders DROP FOREIGN KEY purchase_orders_provider_id_foreign');
            DB::connection('tenant')->statement('ALTER TABLE purchase_orders MODIFY provider_id BIGINT UNSIGNED NOT NULL');
            DB::connection('tenant')->statement('ALTER TABLE purchase_orders ADD CONSTRAINT purchase_orders_provider_id_foreign FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE');
        }
    }
};
