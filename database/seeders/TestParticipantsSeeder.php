<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;

class TestParticipantsSeeder extends Seeder
{
    public function run(): void
    {
        $hashed = Hash::make('Passw0rd!');
        $domain = 'example.de';

        // Vor- und Nachnamen-Pools
        $firstNames = [
            'Anna','Max','Lena','Jonas','Sophie','Paul','Mia','Leon','Laura','Felix',
            'Clara','Tim','Nina','David','Emma','Ben','Marie','Tom','Luca','Lisa',
            'Julia','Erik','Sarah','Jan','Hannah','Philipp','Katrin','Simon','Lea','Tobias'
        ];

        $lastNames = [
            'Müller','Schmidt','Schneider','Fischer','Weber','Meyer','Wagner','Becker','Hoffmann','Schäfer',
            'Koch','Bauer','Richter','Klein','Wolf','Schröder','Neumann','Schwarz','Zimmermann','Braun'
        ];

        for ($i = 1; $i <= 50; $i++) {
            $first = $firstNames[array_rand($firstNames)];
            $last  = $lastNames[array_rand($lastNames)];

            $name  = "{$first} {$last}";
            $email = Str::slug(strtolower($first)).'.'.Str::slug(strtolower($last)).$i.'@'.$domain;

            User::firstOrCreate(
                ['email' => $email],
                [
                    'name'              => $name,
                    'password'          => $hashed,
                    'role'              => 'guest',  // ggf. anpassen
                    'status'            => 1,
                    'email_verified_at' => now(),
                    'remember_token'    => Str::random(10),
                ]
            );
        }
    }
}
