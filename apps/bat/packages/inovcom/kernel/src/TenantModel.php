<?php

namespace InovCom\Kernel;

use Illuminate\Database\Eloquent\Model;

/**
 * Base model for tenant-scoped data. All module models using the tenant
 * database should extend this class.
 */
abstract class TenantModel extends Model
{
    protected $connection = 'tenant';
}
