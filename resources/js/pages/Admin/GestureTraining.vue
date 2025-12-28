<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Separator } from '@/components/ui/separator'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog'
import AppLogo from '@/components/AppLogo.vue'

interface Gesture {
  id: number
  name: string
  label: string
  description?: string
  is_active: boolean
  training_samples: number
  training_data_count?: number
}

interface TrainingData {
  id: number
  gesture_id: number
  landmark_data: any[]
  confidence_score: number
  created_at: string
}

const gestures = ref<Gesture[]>([])
const selectedGesture = ref<Gesture | null>(null)
const trainingData = ref<TrainingData[]>([])
const isTraining = ref(false)
const isLoading = ref(false)
const errorMessage = ref('')
const successMessage = ref('')

// Form states
const showCreateDialog = ref(false)
const showEditDialog = ref(false)
const formData = ref({
  name: '',
  label: '',
  description: '',
  is_active: true
})

// Camera refs
const videoRef = ref<HTMLVideoElement>()
const canvasRef = ref<HTMLCanvasElement>()
const isCameraReady = ref(false)
const capturedLandmarks = ref<any>(null)

let hands: any = null
let camera: any = null

onMounted(async () => {
  await loadGestures()
  await initializeMediaPipe()
})

onUnmounted(() => {
  stopTraining()
  if (camera) {
    camera.stop()
  }
})

const loadGestures = async () => {
  try {
    const response = await fetch('/admin/gestures')
    gestures.value = await response.json()
  } catch (error) {
    console.error('Error loading gestures:', error)
    errorMessage.value = 'Failed to load gestures'
  }
}

const initializeMediaPipe = async () => {
  try {
    const handsModule = await import('@mediapipe/hands')
    const cameraModule = await import('@mediapipe/camera_utils')
    
    hands = new handsModule.Hands({
      locateFile: (file: string) => {
        return `https://cdn.jsdelivr.net/npm/@mediapipe/hands/${file}`
      }
    })

    hands.setOptions({
      maxNumHands: 2,
      modelComplexity: 1,
      minDetectionConfidence: 0.3,
      minTrackingConfidence: 0.3
    })

    hands.onResults(onHandResults)

    if (videoRef.value) {
      camera = new cameraModule.Camera(videoRef.value, {
        onFrame: async () => {
          if (hands && videoRef.value) {
            await hands.send({ image: videoRef.value })
          }
        },
        width: 480,
        height: 480
      })
    }
  } catch (error) {
    console.error('Error initializing MediaPipe:', error)
    errorMessage.value = 'Failed to initialize hand detection'
  }
}

const onHandResults = async (results: any) => {
  if (canvasRef.value) {
    const ctx = canvasRef.value.getContext('2d')
    if (ctx) {
      ctx.clearRect(0, 0, canvasRef.value.width, canvasRef.value.height)
      
      if (results.multiHandLandmarks && results.multiHandLandmarks.length > 0) {
        // Draw hand landmarks with different colors for each hand
        results.multiHandLandmarks.forEach((landmarks: any, index: number) => {
          const isLeftHand = results.multiHandedness[index]?.label === 'Left'
          drawConnectors(ctx, landmarks, HAND_CONNECTIONS, { 
            color: isLeftHand ? '#10b981' : '#f59e0b', 
            lineWidth: 2 
          })
          drawLandmarks(ctx, landmarks, { 
            color: isLeftHand ? '#3b82f6' : '#ef4444', 
            lineWidth: 1, 
            radius: 4 
          })
        })
        
        // Clear error message when hands are detected
        if (errorMessage.value === 'No hand detected. Please position your hand in front of the camera.') {
          errorMessage.value = ''
        }
      } else {
        // No hands detected - show hint text
        if (isTraining.value && ctx) {
          // Draw semi-transparent background for text
          ctx.fillStyle = 'rgba(0, 0, 0, 0.7)'
          ctx.fillRect(canvasRef.value.width / 2 - 150, canvasRef.value.height / 2 - 30, 300, 60)
          
          ctx.fillStyle = '#ffffff'
          ctx.font = 'bold 18px system-ui'
          ctx.textAlign = 'center'
          ctx.fillText('No hand detected', canvasRef.value.width / 2, canvasRef.value.height / 2 - 5)
          
          ctx.font = '14px system-ui'
          ctx.fillStyle = '#e5e7eb'
          ctx.fillText('Please position your hand in front of the camera', canvasRef.value.width / 2, canvasRef.value.height / 2 + 15)
        }
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

// Add drawCapturedLandmarks function like in welcome page
const drawCapturedLandmarks = () => {
  if (!canvasRef.value || !capturedLandmarks.value) return
  
  const ctx = canvasRef.value.getContext('2d')
  if (!ctx) return
  
  const canvas = canvasRef.value
  
  // Clear canvas
  ctx.clearRect(0, 0, canvas.width, canvas.height)
  
  // Draw background
  ctx.fillStyle = '#000000'
  ctx.fillRect(0, 0, canvas.width, canvas.height)
  
  // Draw hand skeleton with larger radius like welcome page
  drawConnectors(ctx, capturedLandmarks.value, HAND_CONNECTIONS, { color: '#10b981', lineWidth: 3 })
  drawLandmarks(ctx, capturedLandmarks.value, { color: '#3b82f6', lineWidth: 2, radius: 6 })
  
  // Draw point numbers
  ctx.fillStyle = '#ffffff'
  ctx.font = '10px system-ui'
  ctx.textAlign = 'center'
  capturedLandmarks.value.forEach((landmark: any, index: number) => {
    const x = landmark.x * canvas.width
    const y = landmark.y * canvas.height
    ctx.fillText(index.toString(), x, y - 10)
  })
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
      isCameraReady.value = true
    }
  } catch (error) {
    console.error('Error starting camera:', error)
    errorMessage.value = 'Failed to access camera'
  }
}

const stopCamera = () => {
  if (camera) {
    camera.stop()
    isCameraReady.value = false
  }
}

const startTraining = async () => {
  if (!selectedGesture.value) return
  
  await startCamera()
  isTraining.value = true
  errorMessage.value = ''
}

const stopTraining = () => {
  isTraining.value = false
  stopCamera()
}

const captureTrainingData = async () => {
  if (!selectedGesture.value || !hands) return
  
  try {
    isLoading.value = true
    
    // Store the current results handler
    let capturedResults: any = null
    
    // Temporarily replace the results handler to capture current frame
    const originalHandler = hands.onResults
    hands.onResults = (results: any) => {
      capturedResults = results
      // Restore original handler
      hands.onResults = originalHandler
    }
    
    // Wait a bit for the next frame
    await new Promise(resolve => setTimeout(resolve, 100))
    
    if (capturedResults && capturedResults.multiHandLandmarks && capturedResults.multiHandLandmarks.length > 0) {
      const allLandmarks = capturedResults.multiHandLandmarks
      const confidence = capturedResults.multiHandedness?.[0]?.score || 0.5
      
      // Store captured landmarks for visualization (show first hand for simplicity)
      capturedLandmarks.value = allLandmarks[0]
      
      const response = await fetch('/admin/training-data', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        },
        body: JSON.stringify({
          gesture_id: selectedGesture.value.id,
          landmark_data: allLandmarks,
          confidence_score: confidence
        })
      })
      
      const result = await response.json()
      
      if (result.success) {
        successMessage.value = 'Training data captured successfully!'
        await loadGestures()
        await loadTrainingData(selectedGesture.value.id)
        
        // Draw captured landmarks visualization
        drawCapturedLandmarks()
        
        setTimeout(() => {
          successMessage.value = ''
        }, 3000)
      }
    } else {
      errorMessage.value = 'No hand detected. Please position your hand in front of the camera.'
    }
  } catch (error) {
    console.error('Error capturing training data:', error)
    errorMessage.value = 'Failed to capture training data'
  } finally {
    isLoading.value = false
  }
}

const selectGesture = async (gesture: Gesture) => {
  selectedGesture.value = gesture
  await loadTrainingData(gesture.id)
}

const loadTrainingData = async (gestureId: number) => {
  try {
    const response = await fetch(`/admin/training-data/${gestureId}`)
    trainingData.value = await response.json()
  } catch (error) {
    console.error('Error loading training data:', error)
  }
}

const createGesture = async () => {
  try {
    const response = await fetch('/admin/gestures', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
      },
      body: JSON.stringify(formData.value)
    })
    
    const result = await response.json()
    
    if (result.success) {
      showCreateDialog.value = false
      formData.value = { name: '', label: '', description: '', is_active: true }
      await loadGestures()
      successMessage.value = 'Gesture created successfully!'
      
      setTimeout(() => {
        successMessage.value = ''
      }, 3000)
    }
  } catch (error) {
    console.error('Error creating gesture:', error)
    errorMessage.value = 'Failed to create gesture'
  }
}

const updateGesture = async () => {
  if (!selectedGesture.value) return
  
  try {
    const response = await fetch(`/admin/gestures/${selectedGesture.value.id}`, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
      },
      body: JSON.stringify(formData.value)
    })
    
    const result = await response.json()
    
    if (result.success) {
      showEditDialog.value = false
      await loadGestures()
      successMessage.value = 'Gesture updated successfully!'
      
      setTimeout(() => {
        successMessage.value = ''
      }, 3000)
    }
  } catch (error) {
    console.error('Error updating gesture:', error)
    errorMessage.value = 'Failed to update gesture'
  }
}

const deleteGesture = async (gesture: Gesture) => {
  if (!confirm(`Are you sure you want to delete "${gesture.label}"?`)) return
  
  try {
    const response = await fetch(`/admin/gestures/${gesture.id}`, {
      method: 'DELETE',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
      }
    })
    
    const result = await response.json()
    
    if (result.success) {
      await loadGestures()
      if (selectedGesture.value?.id === gesture.id) {
        selectedGesture.value = null
        trainingData.value = []
      }
      successMessage.value = 'Gesture deleted successfully!'
      
      setTimeout(() => {
        successMessage.value = ''
      }, 3000)
    }
  } catch (error) {
    console.error('Error deleting gesture:', error)
    errorMessage.value = 'Failed to delete gesture'
  }
}

const deleteTrainingData = async (data: TrainingData) => {
  if (!confirm('Are you sure you want to delete this training data?')) return
  
  try {
    const response = await fetch(`/admin/training-data/${data.id}`, {
      method: 'DELETE',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
      }
    })
    
    const result = await response.json()
    
    if (result.success) {
      await loadTrainingData(data.gesture_id)
      await loadGestures()
      successMessage.value = 'Training data deleted successfully!'
      
      setTimeout(() => {
        successMessage.value = ''
      }, 3000)
    }
  } catch (error) {
    console.error('Error deleting training data:', error)
    errorMessage.value = 'Failed to delete training data'
  }
}

const openEditDialog = (gesture: Gesture) => {
  selectedGesture.value = gesture
  formData.value = {
    name: gesture.name,
    label: gesture.label,
    description: gesture.description || '',
    is_active: gesture.is_active
  }
  showEditDialog.value = true
}
</script>

<template>
  <Head title="Gesture Training" />

  <AppLayout>
    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
          <AppLogo />
          <h1 class="text-2xl font-bold">Gesture Training</h1>
        </div>
        
        <Dialog v-model:open="showCreateDialog">
          <DialogTrigger asChild>
            <Button>Create New Gesture</Button>
          </DialogTrigger>
          <DialogContent>
            <DialogHeader>
              <DialogTitle>Create New Gesture</DialogTitle>
              <DialogDescription>
                Add a new gesture for sign language recognition
              </DialogDescription>
            </DialogHeader>
            <div class="space-y-4">
              <div>
                <Label for="name">Name</Label>
                <Input id="name" v-model="formData.name" placeholder="e.g., hello_gesture" />
              </div>
              <div>
                <Label for="label">Label</Label>
                <Input id="label" v-model="formData.label" placeholder="e.g., Hello" />
              </div>
              <div>
                <Label for="description">Description</Label>
                <Textarea id="description" v-model="formData.description" placeholder="Optional description" />
              </div>
              <div class="flex gap-2">
                <Button @click="createGesture" :disabled="!formData.name || !formData.label">
                  Create
                </Button>
                <Button variant="outline" @click="showCreateDialog = false">
                  Cancel
                </Button>
              </div>
            </div>
          </DialogContent>
        </Dialog>
      </div>

      <!-- Messages -->
      <div v-if="errorMessage" class="p-4 bg-red-50 border border-red-200 rounded-lg text-red-700">
        {{ errorMessage }}
      </div>
      <div v-if="successMessage" class="p-4 bg-green-50 border border-green-200 rounded-lg text-green-700">
        {{ successMessage }}
      </div>

      <div class="grid gap-4 md:grid-cols-2">
        <!-- Gestures List -->
        <Card>
          <CardHeader>
            <CardTitle>Gestures</CardTitle>
            <CardDescription>
              Manage and train gestures for sign language recognition
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div class="space-y-2">
              <div
                v-for="gesture in gestures"
                :key="gesture.id"
                class="p-3 border rounded-lg cursor-pointer hover:bg-gray-50"
                :class="{ 'ring-2 ring-blue-500': selectedGesture?.id === gesture.id }"
                @click="selectGesture(gesture)"
              >
                <div class="flex items-center justify-between">
                  <div>
                    <h3 class="font-semibold">{{ gesture.label }}</h3>
                    <p class="text-sm text-gray-600">{{ gesture.name }}</p>
                    <p class="text-xs text-gray-500">{{ gesture.description }}</p>
                  </div>
                  <div class="flex items-center gap-2">
                    <Badge :variant="gesture.is_active ? 'default' : 'secondary'">
                      {{ gesture.is_active ? 'Active' : 'Inactive' }}
                    </Badge>
                    <Badge variant="outline">
                      {{ gesture.training_data_count || 0 }} samples
                    </Badge>
                  </div>
                </div>
                <div class="flex gap-2 mt-2">
                  <Button size="sm" variant="outline" @click.stop="openEditDialog(gesture)">
                    Edit
                  </Button>
                  <Button size="sm" variant="destructive" @click.stop="deleteGesture(gesture)">
                    Delete
                  </Button>
                </div>
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- Training Interface -->
        <Card>
          <CardHeader>
            <CardTitle>Training Interface</CardTitle>
            <CardDescription>
              Capture training data for selected gesture
            </CardDescription>
          </CardHeader>
          <CardContent class="space-y-4">
            <div v-if="!selectedGesture" class="text-center text-gray-500 py-8">
              Select a gesture to start training
            </div>
            
            <div v-else class="space-y-4">
              <div class="p-3 bg-blue-50 border border-blue-200 rounded-lg">
                <h3 class="font-semibold">{{ selectedGesture.label }}</h3>
                <p class="text-sm text-gray-600">{{ selectedGesture.name }}</p>
                <p class="text-xs text-gray-500">Training samples: {{ selectedGesture.training_data_count || 0 }}</p>
              </div>

              <div class="relative rounded-xl overflow-hidden bg-gradient-to-br from-gray-900 to-gray-800 shadow-inner max-w-md mx-auto">
                <div class="relative aspect-square">
                  <video
                    ref="videoRef"
                    class="absolute inset-0 w-full h-full object-cover"
                    autoplay
                    muted
                  ></video>
                  <canvas
                    ref="canvasRef"
                    class="absolute inset-0 w-full h-full"
                    width="480"
                    height="480"
                  ></canvas>
                </div>
                
                <!-- Status Overlay -->
                <div class="absolute top-4 right-4">
                  <div v-if="isTraining" class="flex items-center space-x-2 bg-red-500 text-white px-3 py-1 rounded-full text-sm font-medium animate-pulse">
                    <div class="w-2 h-2 bg-white rounded-full animate-ping"></div>
                    <span>TRAINING</span>
                  </div>
                  <div v-else-if="isCameraReady" class="flex items-center space-x-2 bg-gray-700 text-white px-3 py-1 rounded-full text-sm font-medium">
                    <div class="w-2 h-2 bg-green-400 rounded-full"></div>
                    <span>READY</span>
                  </div>
                </div>

                <!-- No Camera Placeholder -->
                <div v-if="!isCameraReady && !isTraining" class="absolute inset-0 flex items-center justify-center">
                  <div class="text-center text-white">
                    <svg class="w-16 h-16 mx-auto mb-4 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                    </svg>
                    <p class="text-lg font-medium">Camera Ready</p>
                    <p class="text-sm opacity-75">Click "Start Training" to begin</p>
                  </div>
                </div>
              </div>
              
              <div class="flex gap-2">
                <Button
                  @click="startTraining"
                  :disabled="isTraining || isLoading"
                  class="flex-1"
                >
                  {{ isTraining ? 'Training...' : 'Start Training' }}
                </Button>
                <Button
                  @click="stopTraining"
                  :disabled="!isTraining"
                  variant="outline"
                  class="flex-1"
                >
                  Stop Training
                </Button>
              </div>

              <Button
                @click="captureTrainingData"
                :disabled="!isTraining || isLoading"
                class="w-full"
              >
                {{ isLoading ? 'Capturing...' : 'Capture Sample' }}
              </Button>
            </div>
          </CardContent>
        </Card>
      </div>

      <!-- Training Data -->
      <Card v-if="selectedGesture">
        <CardHeader>
          <CardTitle>Training Data</CardTitle>
          <CardDescription>
            Manage captured training samples for {{ selectedGesture.label }}
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div v-if="trainingData.length === 0" class="text-center text-gray-500 py-8">
            No training data captured yet
          </div>
          
          <div v-else class="space-y-2">
            <div
              v-for="data in trainingData"
              :key="data.id"
              class="flex items-center justify-between p-3 border rounded-lg"
            >
              <div>
                <p class="text-sm font-medium">Sample {{ data.id }}</p>
                <p class="text-xs text-gray-500">
                  Confidence: {{ Math.round(data.confidence_score * 100) }}%
                </p>
                <p class="text-xs text-gray-500">
                  Created: {{ new Date(data.created_at).toLocaleString() }}
                </p>
              </div>
              <Button
                size="sm"
                variant="destructive"
                @click="deleteTrainingData(data)"
              >
                Delete
              </Button>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Edit Dialog -->
      <Dialog v-model:open="showEditDialog">
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Edit Gesture</DialogTitle>
            <DialogDescription>
              Update gesture information
            </DialogDescription>
          </DialogHeader>
          <div class="space-y-4">
            <div>
              <Label for="edit-name">Name</Label>
              <Input id="edit-name" v-model="formData.name" />
            </div>
            <div>
              <Label for="edit-label">Label</Label>
              <Input id="edit-label" v-model="formData.label" />
            </div>
            <div>
              <Label for="edit-description">Description</Label>
              <Textarea id="edit-description" v-model="formData.description" />
            </div>
            <div class="flex items-center gap-2">
              <input
                type="checkbox"
                id="edit-active"
                v-model="formData.is_active"
                class="rounded"
              />
              <Label for="edit-active">Active</Label>
            </div>
            <div class="flex gap-2">
              <Button @click="updateGesture" :disabled="!formData.name || !formData.label">
                Update
              </Button>
              <Button variant="outline" @click="showEditDialog = false">
                Cancel
              </Button>
            </div>
          </div>
        </DialogContent>
      </Dialog>
    </div>
  </AppLayout>
</template>
