<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable,HasRoles;


        protected $guarded = [];         // or fillable, as you prefer
    protected $guard_name = 'web';   // important for Spatie

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password','region'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }


    /*----------------------------------------------
   |  Custom Scopes for ATAI Roles
   *----------------------------------------------*/

    public function scopeSalesmen($query)
    {
        return $query->role([
            'sales_eastern',
            'sales_central',
            'sales_western',
        ]);
    }

    public function scopeCoordinators($query)
    {
        return $query->role([
            'project_coordinator_eastern',
            'project_coordinator_western',
        ]);
    }

    public function scopeEstimators($query)
    {
        return $query->role(['estimator']);
    }

    public function scopeRegionalUsers($query)
    {
        return $query->role([
            'sales_eastern',
            'sales_central',
            'sales_western',
            'project_coordinator_eastern',
            'project_coordinator_western',
        ]);
    }
}
