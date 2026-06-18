<?php

declare(strict_types=1);

namespace Vimatech\Invitation\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Vimatech\Invitation\Concerns\HasInvitations;

class Project extends Model
{
    use HasInvitations;

    protected $guarded = [];
}
