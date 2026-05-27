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
    use HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'name',
        'paternal_surname',
        'maternal_surname',
        'email',
        'personal_email',
        'personal_phone',
        'company_phone',
        'birthdate',
        'gender',
        'address',
        'department',
        'province',
        'district',
        'profile_photo_path',
        'password',
        'telegram_chat_id',
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
     * Route notifications for the Telegram channel.
     *
     * @return string|null
     */
    public function routeNotificationForTelegram()
    {
        return $this->telegram_chat_id;
    }

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
            'birthdate' => 'date',
        ];
    }

    // Accessors
    public function getFullNameAttribute()
    {
        return "{$this->name} {$this->paternal_surname} {$this->maternal_surname}";
    }

    public function getAgeAttribute()
    {
        return $this->birthdate instanceof \Carbon\Carbon ? $this->birthdate->age : null;
    }

    public function getAvatarUrlAttribute()
    {
        return $this->profile_photo_path
            ? asset('storage/'.$this->profile_photo_path)
            : 'https://ui-avatars.com/api/?name='.urlencode($this->name).'&color=7F9CF5&background=EBF4FF';
    }

    public function getFrameClassAttribute()
    {
        if ($this->hasRole('admin')) {
            return 'ring-2 ring-offset-2 ring-cyan-400 shadow-[0_0_10px_rgba(34,211,238,0.35)]';
        }

        if ($this->hasAnyRole(['qa_manager', 'qa_coordinator', 'qa_monitor'])) {
            return 'ring-2 ring-offset-2 ring-indigo-400 shadow-[0_0_8px_rgba(99,102,241,0.25)]';
        }

        if ($this->hasAnyRole(['manager', 'supervisor'])) {
            return 'ring-2 ring-offset-2 ring-gray-300 shadow-[0_0_6px_rgba(209,213,219,0.25)]';
        }

        return 'ring-1 ring-gray-200 dark:ring-gray-700';
    }

    public function monitors()
    {
        return $this->hasMany(User::class, 'supervisor_id');
    }

    public function managedCampaigns()
    {
        return $this->belongsToMany(Campaign::class, 'campaign_managers', 'user_id', 'campaign_id');
    }

    public function supervisorAssignments()
    {
        return $this->hasMany(\App\Models\CampaignUserAssignment::class, 'supervisor_id');
    }

    public function agentAssignments()
    {
        return $this->hasMany(\App\Models\CampaignUserAssignment::class, 'agent_id');
    }
}
