<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Separator } from '@/components/ui/separator'
import AppLogo from '@/components/AppLogo.vue'

interface Gesture {
  id: number
  name: string
  label: string
}

interface DetectedGesture {
  gesture: Gesture | null
  confidence: number
  raw_confidence: number
}

const props = defineProps<{
  gestures: Gesture[]
}>()

const videoRef = ref<HTMLVideoElement>()
const canvasRef = ref<HTMLCanvasElement>()
const isDetecting = ref(false)
const currentGesture = ref<DetectedGesture | null>(null)
const detectedGestures = ref<DetectedGesture[]>([])
const sentence = ref('')
const isLoading = ref(false)
const errorMessage = ref('')

let hands: any = null
let camera: any = null
let detectionInterval: any = null

onMounted(async () => {
  await initializeMediaPipe()
})

onUnmounted(() => {
  stopDetection()
  if (camera) {
    camera.stop()
  }
})

const initializeMediaPipe = async () => {
  try {
    // Load MediaPipe Hands
    const handsModule = await import('@mediapipe/hands')
    const cameraModule = await import('@mediapipe/camera_utils')
    
    hands = new handsModule.Hands({
      locateFile: (file: string) => {
        return `https://cdn.jsdelivr.net/npm/@mediapipe/hands/${file}`
      }
    })

    hands.setOptions({
      maxNumHands: 1,
      modelComplexity: 1,
      minDetectionConfidence: 0.5,
      minTrackingConfidence: 0.5
    })

    hands.onResults(onHandResults)

    // Setup camera
    if (videoRef.value) {
      camera = new cameraModule.Camera(videoRef.value, {
        onFrame: async () => {
          if (hands && videoRef.value) {
            await hands.send({ image: videoRef.value })
          }
        },
        width: 640,
        height: 480
      })
    }
  } catch (error) {
    console.error('Error initializing MediaPipe:', error)
    errorMessage.value = 'Failed to initialize hand detection. Please check your camera permissions.'
  }
}

const onHandResults = async (results: any) => {
  if (canvasRef.value && results.multiHandLandmarks) {
    const ctx = canvasRef.value.getContext('2d')
    if (ctx) {
      ctx.clearRect(0, 0, canvasRef.value.width, canvasRef.value.height)
      
      // Draw hand landmarks
      for (const landmarks of results.multiHandLandmarks) {
        // Draw connections
        drawConnectors(ctx, landmarks, HAND_CONNECTIONS, { color: '#00FF00', lineWidth: 2 })
        // Draw landmarks
        drawLandmarks(ctx, landmarks, { color: '#FF0000', lineWidth: 1, radius: 3 })
      }

      // If detecting and we have landmarks, send to backend
      if (isDetecting.value && results.multiHandLandmarks.length > 0) {
        await detectGesture(results.multiHandLandmarks[0], results.multiHandedness[0]?.score || 0)
      }
    }
  }
}

const drawConnectors = (ctx: CanvasRenderingContext2D, landmarks: any[], connections: any[], style: any) => {
  ctx.strokeStyle = style.color
  ctx.lineWidth = style.lineWidth
  
  for (const connection of connections) {
    const start = landmarks[connection[0]]
    const end = landmarks[connection[1]]
    
    ctx.beginPath()
    ctx.moveTo(start.x * ctx.canvas.width, start.y * ctx.canvas.height)
    ctx.lineTo(end.x * ctx.canvas.width, end.y * ctx.canvas.height)
    ctx.stroke()
  }
}

const drawLandmarks = (ctx: CanvasRenderingContext2D, landmarks: any[], style: any) => {
  ctx.fillStyle = style.color
  
  for (const landmark of landmarks) {
    ctx.beginPath()
    ctx.arc(
      landmark.x * ctx.canvas.width,
      landmark.y * ctx.canvas.height,
      style.radius,
      0,
      2 * Math.PI
    )
    ctx.fill()
  }
}

const HAND_CONNECTIONS = [
  [0, 1], [1, 2], [2, 3], [3, 4],  // Thumb
  [0, 5], [5, 6], [6, 7], [7, 8],  // Index finger
  [5, 9], [9, 10], [10, 11], [11, 12],  // Middle finger
  [9, 13], [13, 14], [14, 15], [15, 16],  // Ring finger
  [13, 17], [17, 18], [18, 19], [19, 20],  // Pinky
  [0, 17]  // Palm
]

const startCamera = async () => {
  try {
    if (camera) {
      await camera.start()
    }
  } catch (error) {
    console.error('Error starting camera:', error)
    errorMessage.value = 'Failed to access camera. Please check permissions.'
  }
}

const stopCamera = () => {
  if (camera) {
    camera.stop()
  }
}

const startDetection = async () => {
  if (!camera) {
    await startCamera()
  }
  
  isDetecting.value = true
  errorMessage.value = ''
  
  // Start continuous detection
  detectionInterval = setInterval(() => {
    // Detection happens in onHandResults callback
  }, 1000) // Detect every second
}

const stopDetection = () => {
  isDetecting.value = false
  if (detectionInterval) {
    clearInterval(detectionInterval)
    detectionInterval = null
  }
}

const detectGesture = async (landmarks: any[], confidence: number) => {
  try {
    isLoading.value = true
    
    const response = await fetch('/sign-language/detect', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
      },
      body: JSON.stringify({
        landmarkData: landmarks,
        confidence: confidence
      })
    })
    
    const result = await response.json()
    
    if (result.gesture) {
      currentGesture.value = result
      detectedGestures.value.push(result)
      updateSentence()
    }
  } catch (error) {
    console.error('Error detecting gesture:', error)
  } finally {
    isLoading.value = false
  }
}

const updateSentence = () => {
  const words = detectedGestures.value
    .filter(g => g.gesture && g.confidence > 0.7)
    .map(g => g.gesture?.label || '')
    .filter(word => word.length > 0)
  
  sentence.value = words.join(' ')
}

const clearSentence = () => {
  detectedGestures.value = []
  sentence.value = ''
  currentGesture.value = null
}

const saveSentence = async () => {
  if (!sentence.value.trim()) return
  
  try {
    const response = await fetch('/sign-language/save-sentence', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
      },
      body: JSON.stringify({
        sentence: sentence.value,
        gestures: detectedGestures.value.filter(g => g.gesture)
      })
    })
    
    const result = await response.json()
    
    if (result.success) {
      clearSentence()
      // Show success message
      alert('Sentence saved successfully!')
    }
  } catch (error) {
    console.error('Error saving sentence:', error)
  }
}

const getConfidenceColor = (confidence: number) => {
  if (confidence > 0.8) return 'bg-green-500'
  if (confidence > 0.6) return 'bg-yellow-500'
  return 'bg-red-500'
}
</script>

<template>
  <Head title="Sign Language Detection" />

  <AppLayout>
    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
          <AppLogo />
          <h1 class="text-2xl font-bold">Sign Language Detection</h1>
        </div>
      </div>

      <div class="grid gap-4 md:grid-cols-2">
        <!-- Camera View -->
        <Card>
          <CardHeader>
            <CardTitle>Camera View</CardTitle>
            <CardDescription>
              Position your hand in front of the camera for gesture detection
            </CardDescription>
          </CardHeader>
          <CardContent class="space-y-4">
            <div class="relative">
              <video
                ref="videoRef"
                class="w-full rounded-lg bg-black"
                autoplay
                playsinline
              />
              <canvas
                ref="canvasRef"
                class="absolute top-0 left-0 w-full h-full"
                width="640"
                height="480"
              />
            </div>
            
            <div class="flex gap-2">
              <Button
                @click="startDetection"
                :disabled="isDetecting || isLoading"
                class="flex-1"
              >
                {{ isDetecting ? 'Detecting...' : 'Start Detection' }}
              </Button>
              <Button
                @click="stopDetection"
                :disabled="!isDetecting"
                variant="outline"
                class="flex-1"
              >
                Stop Detection
              </Button>
            </div>

            <div v-if="errorMessage" class="text-sm text-red-600">
              {{ errorMessage }}
            </div>
          </CardContent>
        </Card>

        <!-- Detection Results -->
        <Card>
          <CardHeader>
            <CardTitle>Detection Results</CardTitle>
            <CardDescription>
              Real-time gesture recognition results
            </CardDescription>
          </CardHeader>
          <CardContent class="space-y-4">
            <div v-if="currentGesture?.gesture" class="p-4 border rounded-lg">
              <div class="flex items-center justify-between">
                <div>
                  <h3 class="font-semibold">{{ currentGesture.gesture.label }}</h3>
                  <p class="text-sm text-gray-600">{{ currentGesture.gesture.name }}</p>
                </div>
                <Badge :class="getConfidenceColor(currentGesture.confidence)">
                  {{ Math.round(currentGesture.confidence * 100) }}%
                </Badge>
              </div>
            </div>

            <div v-else class="p-4 border rounded-lg text-center text-gray-500">
              No gesture detected yet
            </div>

            <Separator />

            <div>
              <h3 class="font-semibold mb-2">Detected Gestures</h3>
              <div class="space-y-2 max-h-40 overflow-y-auto">
                <div
                  v-for="(gesture, index) in detectedGestures"
                  :key="index"
                  class="flex items-center justify-between p-2 border rounded"
                >
                  <span class="text-sm">{{ gesture.gesture?.label || 'Unknown' }}</span>
                  <Badge variant="outline" class="text-xs">
                    {{ Math.round(gesture.confidence * 100) }}%
                  </Badge>
                </div>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      <!-- Sentence Formation -->
      <Card>
        <CardHeader>
          <CardTitle>Sentence Formation</CardTitle>
          <CardDescription>
            Detected gestures are automatically combined into a sentence
          </CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="p-4 bg-gray-50 rounded-lg min-h-[60px]">
            <p class="text-lg">{{ sentence || 'Start detecting gestures to form a sentence...' }}</p>
          </div>
          
          <div class="flex gap-2">
            <Button @click="saveSentence" :disabled="!sentence.trim()" class="flex-1">
              Save Sentence
            </Button>
            <Button @click="clearSentence" variant="outline" class="flex-1">
              Clear
            </Button>
          </div>
        </CardContent>
      </Card>

      <!-- Available Gestures Reference -->
      <Card>
        <CardHeader>
          <CardTitle>Available Gestures</CardTitle>
          <CardDescription>
            Reference for all trained gestures
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div class="grid gap-2 md:grid-cols-3 lg:grid-cols-4">
            <div
              v-for="gesture in gestures"
              :key="gesture.id"
              class="p-3 border rounded-lg text-center"
            >
              <h4 class="font-semibold">{{ gesture.label }}</h4>
              <p class="text-sm text-gray-600">{{ gesture.name }}</p>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>
