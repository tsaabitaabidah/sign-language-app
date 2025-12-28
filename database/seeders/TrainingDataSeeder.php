<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Gesture;
use App\Models\TrainingData;

class TrainingDataSeeder extends Seeder
{
    public function run(): void
    {
        $gestures = Gesture::all();
        
        foreach ($gestures as $gesture) {
            // Create 5-10 training samples per gesture
            $sampleCount = rand(5, 10);
            
            for ($i = 0; $i < $sampleCount; $i++) {
                $landmarkData = $this->generateSampleLandmarks($gesture->name);
                $confidence = 0.8 + (rand(0, 20) / 100); // 0.8 to 1.0
                $handCount = ($gesture->supports_dual_hand && rand(0, 1)) ? 2 : 1;
                
                TrainingData::create([
                    'gesture_id' => $gesture->id,
                    'landmark_data' => $landmarkData,
                    'confidence_score' => $confidence,
                    'hand_count' => $handCount,
                    'notes' => "Sample " . ($i + 1) . " for " . $gesture->label,
                ]);
            }
        }

        $this->command->info('Training data seeded successfully!');
    }

    /**
     * Generate sample landmark data for testing purposes
     */
    private function generateSampleLandmarks(string $gestureName): array
    {
        // Base hand landmarks (21 points) - normalized coordinates
        $baseLandmarks = [
            ['x' => 0.5, 'y' => 0.5, 'z' => 0.0],  // Wrist (0)
            ['x' => 0.45, 'y' => 0.4, 'z' => -0.05], // Thumb CMC (1)
            ['x' => 0.42, 'y' => 0.35, 'z' => -0.08], // Thumb MCP (2)
            ['x' => 0.4, 'y' => 0.3, 'z' => -0.1], // Thumb IP (3)
            ['x' => 0.38, 'y' => 0.25, 'z' => -0.12], // Thumb tip (4)
            ['x' => 0.48, 'y' => 0.35, 'z' => -0.03], // Index MCP (5)
            ['x' => 0.46, 'y' => 0.25, 'z' => -0.05], // Index PIP (6)
            ['x' => 0.45, 'y' => 0.15, 'z' => -0.06], // Index DIP (7)
            ['x' => 0.44, 'y' => 0.05, 'z' => -0.07], // Index tip (8)
            ['x' => 0.5, 'y' => 0.33, 'z' => -0.02], // Middle MCP (9)
            ['x' => 0.49, 'y' => 0.23, 'z' => -0.04], // Middle PIP (10)
            ['x' => 0.48, 'y' => 0.13, 'z' => -0.05], // Middle DIP (11)
            ['x' => 0.47, 'y' => 0.03, 'z' => -0.06], // Middle tip (12)
            ['x' => 0.52, 'y' => 0.32, 'z' => -0.01], // Ring MCP (13)
            ['x' => 0.51, 'y' => 0.22, 'z' => -0.03], // Ring PIP (14)
            ['x' => 0.5, 'y' => 0.12, 'z' => -0.04], // Ring DIP (15)
            ['x' => 0.49, 'y' => 0.02, 'z' => -0.05], // Ring tip (16)
            ['x' => 0.54, 'y' => 0.31, 'z' => 0.0], // Pinky MCP (17)
            ['x' => 0.53, 'y' => 0.21, 'z' => -0.02], // Pinky PIP (18)
            ['x' => 0.52, 'y' => 0.11, 'z' => -0.03], // Pinky DIP (19)
            ['x' => 0.51, 'y' => 0.01, 'z' => -0.04], // Pinky tip (20)
        ];

        // Modify landmarks based on gesture type
        switch ($gestureName) {
            case 'hello':
                // Open hand - fingers extended
                for ($i = 8; $i <= 20; $i++) {
                    $baseLandmarks[$i]['y'] -= 0.05; // Extend fingers
                }
                break;
                
            case 'thank_you':
                // Hand slightly tilted
                for ($i = 0; $i < 21; $i++) {
                    $baseLandmarks[$i]['x'] += 0.05;
                    $baseLandmarks[$i]['z'] += 0.02;
                }
                break;
                
            case 'please':
                // Circular motion - hand more closed
                for ($i = 8; $i <= 20; $i++) {
                    $baseLandmarks[$i]['y'] += 0.03; // Close fingers slightly
                }
                break;
                
            case 'yes':
                // Closed fist
                for ($i = 8; $i <= 20; $i++) {
                    $baseLandmarks[$i]['y'] += 0.08; // Close fingers
                    $baseLandmarks[$i]['x'] += 0.02;
                }
                break;
                
            case 'no':
                // Hand flat, fingers together
                for ($i = 8; $i <= 20; $i++) {
                    $baseLandmarks[$i]['x'] = 0.5; // Align fingers
                }
                break;
                
            case 'help':
                // Hand raised, palm forward
                for ($i = 0; $i < 21; $i++) {
                    $baseLandmarks[$i]['y'] -= 0.1; // Raise hand
                    $baseLandmarks[$i]['z'] += 0.05; // Palm forward
                }
                break;
                
            case 'good':
                // Thumbs up
                $baseLandmarks[4]['y'] -= 0.15; // Thumb up
                for ($i = 8; $i <= 20; $i++) {
                    $baseLandmarks[$i]['y'] += 0.1; // Other fingers down
                }
                break;
                
            case 'bad':
                // Thumbs down
                $baseLandmarks[4]['y'] += 0.15; // Thumb down
                for ($i = 8; $i <= 20; $i++) {
                    $baseLandmarks[$i]['y'] += 0.1; // Other fingers down
                }
                break;
                
            case 'sorry':
                // Hand over heart position
                for ($i = 0; $i < 21; $i++) {
                    $baseLandmarks[$i]['x'] -= 0.1; // Move to left (heart position)
                    $baseLandmarks[$i]['y'] += 0.05;
                }
                break;
                
            case 'love':
                // Crossed arms simulation - hand rotated
                for ($i = 0; $i < 21; $i++) {
                    $baseLandmarks[$i]['z'] += 0.1; // Rotate hand
                    $baseLandmarks[$i]['y'] += 0.02;
                }
                break;
                
            case 'i_love_you':
                // I Love You sign - thumb up, index and pinky up
                $baseLandmarks[4]['y'] -= 0.12; // Thumb up
                $baseLandmarks[8]['y'] -= 0.1;  // Index up
                $baseLandmarks[20]['y'] -= 0.08; // Pinky up
                // Other fingers down
                for ($i = 12; $i <= 16; $i++) {
                    $baseLandmarks[$i]['y'] += 0.08;
                }
                break;
        }

        // Add random variations to simulate different users
        foreach ($baseLandmarks as $index => $landmark) {
            $baseLandmarks[$index]['x'] += $this->randomOffset(-0.05, 0.05);
            $baseLandmarks[$index]['y'] += $this->randomOffset(-0.05, 0.05);
            $baseLandmarks[$index]['z'] += $this->randomOffset(-0.03, 0.03);
        }

        return $baseLandmarks;
    }

    private function randomOffset(float $min, float $max): float
    {
        return $min + (mt_rand() / mt_getrandmax()) * ($max - $min);
    }
}
