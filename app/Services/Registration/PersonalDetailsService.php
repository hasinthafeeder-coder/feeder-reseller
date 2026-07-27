<?php

namespace App\Services\Registration;

use Feeder\Core\Models\User;
use Feeder\Core\Models\UserProfile;
use Feeder\Core\Models\UuidService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PersonalDetailsService
{
    public function save(array $data): void
    {
        DB::transaction(function () use ($data) {
            $user = User::query()
                ->where('uuid', $data['user_uuid'])
                ->first();

            if (!$user) {
                throw ValidationException::withMessages([
                    'user_uuid' => 'Invalid Registration.',
                ]);
            }

            if ($user->profile()->exists()) {
                throw ValidationException::withMessages([
                    'user_uuid' => 'Personal details already saved.',
                ]);
            }

            $photo = $data['profile_photo']->store('/registration/profile-photos', 'public');

            UserProfile::create([
                'uuid' => UuidService::generate(),
                'user_id' => $user->id,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'nic' => $data['nic'],
                'address' => $data['address'],
                'profile_photo' => $photo,
            ]);
        });
    }
}
