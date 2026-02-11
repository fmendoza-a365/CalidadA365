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
    use HasFactory, Notifiable, HasRoles;

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
        return $this->birthdate ? $this->birthdate->age : null;
    }

    public function getAvatarUrlAttribute()
    {
        return $this->profile_photo_path
            ? asset('storage/' . $this->profile_photo_path)
            : 'https://ui-avatars.com/api/?name=' . urlencode($this->name) . '&color=7F9CF5&background=EBF4FF';
    }

    public function getFrameClassAttribute()
    {
        // MOCK LOGIC for Demo Purposes based on Email/Role
        // In production, this would calculate: $this->evaluations()->avg('score')

        // 1. Admin & Top Performers -> Diamond (Neon Cyan)
        if ($this->hasRole('admin') || str_contains($this->email, 'lucia')) {
            return 'ring-4 ring-offset-2 ring-cyan-400 shadow-[0_0_15px_rgba(34,211,238,0.6)] animate-pulse-slow';
        }

        // 2. High Performers / QA -> Gold (Shiny Gold)
        if ($this->hasRole('qa_monitor') || str_contains($this->email, 'sofia')) {
            return 'ring-4 ring-offset-2 ring-yellow-400 shadow-[0_0_10px_rgba(250,204,21,0.5)]';
        }

        // 3. Supervisors -> Silver (Metallic)
        if ($this->hasRole('supervisor') || str_contains($this->email, 'carlos')) {
            return 'ring-4 ring-offset-2 ring-gray-300 shadow-[0_0_8px_rgba(209,213,219,0.4)]';
        }

        // 4. Standard -> Bronze (Orange/Brown)
        if (str_contains($this->email, 'miguel')) {
            return 'ring-4 ring-offset-2 ring-orange-700/60'; // Bronze-ish
        }

        // 5. Low Performance -> Alert (Red)
        if (str_contains($this->email, 'risk')) {
            return 'ring-2 ring-offset-1 ring-red-500/80';
        }

        return 'ring-1 ring-gray-200 dark:ring-gray-700'; // Default clean
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

    public function dashboardWidgets()
    {
        return $this->hasMany(\App\Models\DashboardWidget::class);
    }
}
