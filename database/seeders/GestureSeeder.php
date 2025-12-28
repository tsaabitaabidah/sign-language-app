<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Gesture;

class GestureSeeder extends Seeder
{
    public function run(): void
    {
        $gestures = [
            [
                'name' => 'hello',
                'label' => 'Hello',
                'description' => 'Open hand with fingers extended',
                'is_active' => true,
                'supports_dual_hand' => false,
            ],
            [
                'name' => 'thank_you',
                'label' => 'Thank You',
                'description' => 'Hand moving from chin to outward',
                'is_active' => true,
                'supports_dual_hand' => false,
            ],
            [
                'name' => 'please',
                'label' => 'Please',
                'description' => 'Hand rubbing in circular motion on chest',
                'is_active' => true,
                'supports_dual_hand' => false,
            ],
            [
                'name' => 'yes',
                'label' => 'Yes',
                'description' => 'Closed fist moving up and down',
                'is_active' => true,
                'supports_dual_hand' => false,
            ],
            [
                'name' => 'no',
                'label' => 'No',
                'description' => 'Hand moving side to side',
                'is_active' => true,
                'supports_dual_hand' => false,
            ],
            [
                'name' => 'help',
                'label' => 'Help',
                'description' => 'Hand raised with palm facing forward',
                'is_active' => true,
                'supports_dual_hand' => false,
            ],
            [
                'name' => 'good',
                'label' => 'Good',
                'description' => 'Thumbs up gesture',
                'is_active' => true,
                'supports_dual_hand' => false,
            ],
            [
                'name' => 'bad',
                'label' => 'Bad',
                'description' => 'Thumbs down gesture',
                'is_active' => true,
                'supports_dual_hand' => false,
            ],
            [
                'name' => 'sorry',
                'label' => 'Sorry',
                'description' => 'Hand over heart in circular motion',
                'is_active' => true,
                'supports_dual_hand' => false,
            ],
            [
                'name' => 'love',
                'label' => 'Love',
                'description' => 'Crossed arms over chest',
                'is_active' => true,
                'supports_dual_hand' => true,
            ],
            [
                'name' => 'i_love_you',
                'label' => 'I Love You',
                'description' => 'Point to self, then cross arms over chest',
                'is_active' => true,
                'supports_dual_hand' => false,
            ],
        ];

        foreach ($gestures as $gesture) {
            Gesture::create($gesture);
        }

        $this->command->info('Default gestures seeded successfully!');
    }
}
