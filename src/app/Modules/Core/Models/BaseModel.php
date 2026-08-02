<?php

declare(strict_types=1);

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;

abstract class BaseModel extends Model
{
    /**
     * Guard nothing by default.
     */
    protected $guarded = [];

    /**
     * Enable strict lazy loading in development.
     */
    protected static bool $modelsShouldPreventLazyLoading = true;
}