<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Select } from '@/components/ui/select'
import { Badge } from '@/components/ui/badge'
import AppLayout from '@/layouts/AppLayout.vue'
import { ArrowLeft, Camera, Save, Hand } from 'lucide-vue-next'

interface Gesture {
  id: number
  name: string
  label: string
}

interface Props {
  gestures: Gesture[]
}

const props = defineProps<Props>()

const selectedGesture = ref('')
const landmarkData = ref<any>(null)
const confidence = ref(0)
const notes = ref('')
const isCapturing = ref(false)
const isDetecting = ref(false)
const videoElement = ref<HTMLVideoElement | null>(null)
const canvasElement = ref<HTMLCanvasElement | null>(null)
const overlayCanvas = ref<HTMLCanvasElement | null>(null)
const detectionStatus = ref('Initializing camera...')

// MediaPipe variables
let hands: any = null
let camera: any = null
let latestLandmarks: any = null
let latestConfidence: number = 0.5

const startCamera = async () => {
  try {
    detectionStatus.value = 'Loading MediaPipe...'
    await initializeMediaPipe()
  } catch (error) {
    console.error('Error starting camera:', error)
    detectionStatus.value = 'Camera initialization failed'
  }
}

const initializeMediaPipe = async () => {
  try {
    // Import MediaPipe modules
    const handsModule = await import('@mediapipe/hands')
    const cameraModule = await import('@mediapipe/camera_utils')
    
    // Initialize Hands
    hands = new handsModule.Hands({
      locateFile: (file: string) => {
        return `https://cdn.jsdelivr.net/npm/@mediapipe/hands/${file}`
      }
    })

    hands.setOptions({
      maxNumHands: 2,
      modelComplexity: 1,
      minDetectionConfidence: 0.5,
      minTrackingConfidence: 0.5
    })

    hands.onResults(onHandResults)

    // Start camera
    if (videoElement.value) {
      camera = new cameraModule.Camera(videoElement.value, {
        onFrame: async () => {
          if (hands && videoElement.value) {
            await hands.send({ image: videoElement.value })
          }
        },
        width: 640,
        height: 480
      })
      
      await camera.start()
      isDetecting.value = true
      detectionStatus.value = 'Hand detection active'
    }
  } catch (error) {
    console.error('Error initializing MediaPipe:', error)
    detectionStatus.value = 'Failed to load MediaPipe'
  }
}

const onHandResults = (results: any) => {
  if (!overlayCanvas.value) return
  
  const ctx = overlayCanvas.value.getContext('2d')
  if (!ctx) return
  
  // Clear canvas
  ctx.clearRect(0, 0, overlayCanvas.value.width, overlayCanvas.value.height)
  
  if (results.multiHandLandmarks && results.multiHandLandmarks.length > 0) {
    const handCount = results.multiHandLandmarks.length
    
    // Store all landmarks for capture (array of hands)
    latestLandmarks = results.multiHandLandmarks
    latestConfidence = results.multiHandedness?.[0]?.score || 0.5
    
    // Draw hand skeleton for all detected hands
    results.multiHandLandmarks.forEach((landmarks: any, index: number) => {
      // Use different colors for different hands
      const isLeftHand = results.multiHandedness[index]?.label === 'Left'
      drawConnections(ctx, landmarks, isLeftHand ? '#10b981' : '#f59e0b')
      drawLandmarks(ctx, landmarks, isLeftHand ? '#3b82f6' : '#ef4444')
    })
    
    const handText = handCount === 1 ? '1 hand' : `${handCount} hands`
    detectionStatus.value = `${handText} detected - Confidence: ${(latestConfidence * 100).toFixed(0)}%`
  } else {
    latestLandmarks = null
    detectionStatus.value = 'No hand detected'
  }
}

const drawConnections = (ctx: CanvasRenderingContext2D, landmarks: any[], color: string = '#10b981') => {
  const connections = [
    [0, 1], [1, 2], [2, 3], [3, 4],  // Thumb
    [0, 5], [5, 6], [6, 7], [7, 8],  // Index
    [5, 9], [9, 10], [10, 11], [11, 12],  // Middle
    [9, 13], [13, 14], [14, 15], [15, 16],  // Ring
    [13, 17], [17, 18], [18, 19], [19, 20],  // Pinky
    [0, 17]  // Palm
  ]
  
  ctx.strokeStyle = color
  ctx.lineWidth = 3
  ctx.lineCap = 'round'
  
  connections.forEach(([start, end]) => {
    const startPoint = landmarks[start]
    const endPoint = landmarks[end]
    
    ctx.beginPath()
    ctx.moveTo(startPoint.x * overlayCanvas.value!.width, startPoint.y * overlayCanvas.value!.height)
    ctx.lineTo(endPoint.x * overlayCanvas.value!.width, endPoint.y * overlayCanvas.value!.height)
    ctx.stroke()
  })
}

const drawLandmarks = (ctx: CanvasRenderingContext2D, landmarks: any[], color: string = '#3b82f6') => {
  landmarks.forEach((landmark: any, index: number) => {
    const x = landmark.x * overlayCanvas.value!.width
    const y = landmark.y * overlayCanvas.value!.height
    
    ctx.fillStyle = index === 0 ? '#ef4444' : color
    ctx.beginPath()
    ctx.arc(x, y, index === 0 ? 8 : 5, 0, 2 * Math.PI)
    ctx.fill()
  })
}

const captureGesture = async () => {
  if (!selectedGesture.value) {
    alert('Please select a gesture first.')
    return
  }
  
  if (!latestLandmarks) {
    alert('No hand detected. Please show your hand to the camera.')
    return
  }
  
  isCapturing.value = true
  
  try {
    // Capture frame to canvas
    const ctx = canvasElement.value?.getContext('2d')
    if (ctx && videoElement.value) {
      canvasElement.value!.width = videoElement.value.videoWidth
      canvasElement.value!.height = videoElement.value.videoHeight
      ctx.drawImage(videoElement.value, 0, 0)
    }
    
    // Use REAL MediaPipe landmarks (not mock data!)
    landmarkData.value = latestLandmarks
    
    // Set confidence from MediaPipe or use input value
    if (confidence.value === 0) {
      confidence.value = latestConfidence
    }
    
    console.log('✅ Captured real MediaPipe landmarks:', landmarkData.value.length, 'points')
    
  } catch (error) {
    console.error('Error capturing gesture:', error)
    alert('Failed to capture gesture. Please try again.')
  } finally {
    isCapturing.value = false
  }
}

const saveTrainingData = () => {
  if (!selectedGesture.value || !landmarkData.value) {
    alert('Please select a gesture and capture hand data first.')
    return
  }
  
  console.log('Saving training data:', {
    gesture_id: selectedGesture.value,
    landmark_count: landmarkData.value.length,
    confidence: confidence.value
  })
  
  router.post('/admin/training-data', {
    gesture_id: selectedGesture.value,
    landmark_data: landmarkData.value,
    confidence_score: confidence.value,
    notes: notes.value
  })
}

const formatConfidence = (value: number) => {
  return `${(value * 100).toFixed(1)}%`
}

onMounted(() => {
  startCamera()
})

onUnmounted(() => {
  if (camera) {
    camera.stop()
  }
  if (hands) {
    hands.close()
  }
})
</script>

<template>
  <Head title="Add Training Data" />

  <AppLayout>
    <div class="py-6">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
          <div class="flex items-center">
            <Link href="/admin/training-data">
              <Button variant="outline" size="sm" class="mr-4">
                <ArrowLeft class="w-4 h-4 mr-2" />
                Back
              </Button>
            </Link>
            <div>
              <h1 class="text-2xl font-bold text-gray-900">Add Training Data</h1>
              <p class="text-gray-600">Capture and record gesture training data with MediaPipe</p>
            </div>
          </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <!-- Camera Section -->
          <Card>
            <CardHeader>
              <CardTitle class="flex items-center">
                <Camera class="w-5 h-5 mr-2" />
                Camera Capture
              </CardTitle>
              <CardDescription>
                Position your hand in the camera view and capture gesture data
              </CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
              <!-- Video Feed -->
              <div class="relative bg-black rounded-lg overflow-hidden" style="aspect-ratio: 4/3;">
                <video
                  ref="videoElement"
                  autoplay
                  playsinline
                  muted
                  class="w-full h-full object-cover"
                />
                <canvas
                  ref="canvasElement"
                  class="hidden"
                />
                <canvas
                  ref="overlayCanvas"
                  width="640"
                  height="480"
                  class="absolute top-0 left-0 w-full h-full pointer-events-none"
                />
                
                <!-- Live Badge -->
                <div class="absolute top-4 left-4">
                  <Badge :variant="isDetecting ? 'default' : 'secondary'" :class="isDetecting ? 'bg-red-500' : ''">
                    <div v-if="isDetecting" class="w-2 h-2 bg-white rounded-full mr-2 animate-pulse"></div>
                    {{ isDetecting ? 'LIVE' : 'OFFLINE' }}
                  </Badge>
                </div>
                
                <!-- Hand Detection Status -->
                <div class="absolute top-4 right-4">
                  <Badge :variant="latestLandmarks ? 'default' : 'secondary'" :class="latestLandmarks ? 'bg-green-500' : ''">
                    {{ latestLandmarks ? `✓ ${Array.isArray(latestLandmarks) ? latestLandmarks.length : 1} hand${Array.isArray(latestLandmarks) && latestLandmarks.length > 1 ? 's' : ''} detected` : '⚠ No Hand' }}
                  </Badge>
                </div>
                
                <!-- Status Bar -->
                <div class="absolute bottom-4 left-4 right-4">
                  <div class="bg-black bg-opacity-75 text-white px-3 py-2 rounded text-sm">
                    {{ detectionStatus }}
                  </div>
                </div>
              </div>
              
              <!-- Capture Button -->
              <Button 
                @click="captureGesture"
                :disabled="isCapturing || !selectedGesture || !latestLandmarks"
                class="w-full"
                size="lg"
              >
                <Camera class="w-4 h-4 mr-2" />
                {{ isCapturing ? 'Capturing...' : 'Capture Gesture' }}
              </Button>
              
              <!-- Hint Text -->
              <p class="text-sm text-gray-600 text-center">
                {{ !selectedGesture ? '⚠ Select a gesture first' : 
                   !latestLandmarks ? '⚠ Show your hand to camera' : 
                   '✓ Ready to capture' }}
              </p>
            </CardContent>
          </Card>

          <!-- Form Section -->
          <Card>
            <CardHeader>
              <CardTitle class="flex items-center">
                <Hand class="w-5 h-5 mr-2" />
                Training Data
              </CardTitle>
              <CardDescription>
                Configure the gesture and capture parameters
              </CardDescription>
            </CardHeader>
            <CardContent class="space-y-6">
              <!-- Gesture Selection -->
              <div>
                <Label for="gesture">Gesture *</Label>
                <Select 
                  v-model="selectedGesture" 
                  :options="gestures.map(g => ({ value: g.id.toString(), label: `${g.label} - ${g.name}` }))"
                  placeholder="Select a gesture"
                  class="mt-1"
                />
              </div>

              <!-- Confidence Display -->
              <div>
                <Label for="confidence">Confidence Level</Label>
                <div class="flex items-center gap-3 mt-1">
                  <Input
                    id="confidence"
                    v-model="confidence"
                    type="number"
                    step="0.01"
                    min="0"
                    max="1"
                    placeholder="0.85"
                    class="flex-1"
                  />
                  <Badge v-if="confidence > 0" class="px-3 py-1">
                    {{ formatConfidence(confidence) }}
                  </Badge>
                </div>
                <p class="mt-1 text-sm text-gray-600">
                  Auto-filled from MediaPipe or enter manually (0.0 - 1.0)
                </p>
              </div>

              <!-- Capture Status -->
              <div v-if="landmarkData" class="p-4 bg-green-50 border border-green-200 rounded-lg">
                <div class="flex items-center justify-between mb-2">
                  <span class="text-green-800 font-medium">✓ Capture Successful</span>
                  <Badge variant="default" class="bg-green-600">
                    {{ formatConfidence(confidence) }}
                  </Badge>
                </div>
                <div class="text-sm text-green-700">
                  ✓ {{ Array.isArray(landmarkData) ? `${landmarkData.length} hands` : '1 hand' }} captured
                </div>
                <div class="text-xs text-green-600 mt-1">
                  {{ Array.isArray(landmarkData) ? 
                    `${landmarkData.length} hands with ${landmarkData.reduce((sum, hand) => sum + hand.length, 0)} total landmarks` :
                    `${landmarkData.length} landmarks captured` }}
                </div>
                <div class="text-xs text-green-600 mt-1">
                  First point: ({{ (Array.isArray(landmarkData) ? landmarkData[0] : landmarkData)[0].x.toFixed(3) }}, {{ (Array.isArray(landmarkData) ? landmarkData[0] : landmarkData)[0].y.toFixed(3) }}, {{ (Array.isArray(landmarkData) ? landmarkData[0] : landmarkData)[0].z.toFixed(3) }})
                </div>
              </div>

              <!-- Notes -->
              <div>
                <Label for="notes">Notes (Optional)</Label>
                <Textarea
                  id="notes"
                  v-model="notes"
                  placeholder="Add any notes about this training data..."
                  rows="3"
                  class="mt-1"
                />
              </div>

              <!-- Save Button -->
              <Button 
                @click="saveTrainingData"
                :disabled="!selectedGesture || !landmarkData"
                class="w-full"
                size="lg"
              >
                <Save class="w-4 h-4 mr-2" />
                Save Training Data
              </Button>
            </CardContent>
          </Card>
        </div>

        <!-- Instructions -->
        <Card class="mt-6">
          <CardHeader>
            <CardTitle>📋 Instructions</CardTitle>
          </CardHeader>
          <CardContent>
            <ol class="list-decimal list-inside space-y-2 text-sm text-gray-700">
              <li><strong>Select a gesture</strong> from the dropdown menu</li>
              <li><strong>Wait for hand detection</strong> - green lines should appear over your hand</li>
              <li><strong>Position your hand clearly</strong> - make sure lighting is good and hand is visible</li>
              <li><strong>Form the gesture</strong> correctly and hold steady</li>
              <li><strong>Click "Capture Gesture"</strong> when ready</li>
              <li><strong>Verify capture success</strong> - you should see landmark coordinates</li>
              <li><strong>Add notes</strong> (optional) and click "Save Training Data"</li>
            </ol>
            <div class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
              <p class="text-sm text-blue-800">
                <strong>💡 Tip:</strong> Capture 5-10 samples of each gesture from different angles and lighting conditions for best accuracy.
              </p>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  </AppLayout>
</template>
