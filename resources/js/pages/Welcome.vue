<script setup>
import { ref, onMounted, onUnmounted, nextTick } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Textarea } from '@/components/ui/textarea'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Separator } from '@/components/ui/separator'
import { Progress } from '@/components/ui/progress'
import { dashboard, login, register } from '@/routes'

const props = defineProps({
  canRegister: {
    type: Boolean,
    default: true
  },
  gestures: {
    type: Array,
    default: () => []
  }
})

// Camera and detection refs
const videoRef = ref(null)
const canvasRef = ref(null)
const landmarksCanvas = ref(null)
const isDetecting = ref(false)
const isCameraReady = ref(false)
const errorMessage = ref('')
const successMessage = ref('')

// Detection results
const detectedGesture = ref('')
const confidence = ref(0)
const realtimeGesture = ref('')
const realtimeConfidence = ref(0)
const isRealtimeDetecting = ref(false)
const sentence = ref('')
const detectedGestures = ref([])
const currentHandCount = ref(0)
const handData = ref([])

// Performance metrics
const detectionHistory = ref([])
const averageConfidence = ref(0)

// Landmarks and visualization
const capturedLandmarks = ref(null)
const showCapturedLandmarks = ref(false)

// MediaPipe instances
let hands = null
let camera = null
let realtimeDetectionInterval = null
let latestLandmarks = null
let latestConfidence = 0

onMounted(async () => {
  await initializeMediaPipe()
  loadDetectionHistory()
})

onUnmounted(() => {
  stopDetection()
  stopRealtimeDetection()
  if (camera) {
    camera.stop()
  }
})

const initializeMediaPipe = async () => {
  try {
    const [handsModule, cameraModule] = await Promise.all([
      import('@mediapipe/hands'),
      import('@mediapipe/camera_utils')
    ])
    
    hands = new handsModule.Hands({
      locateFile: (file) => {
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
    errorMessage.value = 'Failed to initialize hand detection. Please check your camera permissions.'
  }
}

const onHandResults = async (results) => {
  if (canvasRef.value) {
    const ctx = canvasRef.value.getContext('2d')
    if (ctx) {
      ctx.clearRect(0, 0, canvasRef.value.width, canvasRef.value.height)
      
      if (results.multiHandLandmarks && results.multiHandLandmarks.length > 0) {
        currentHandCount.value = results.multiHandLandmarks.length
        handData.value = results.multiHandLandmarks
        
        // Update latest landmarks (use first hand for single-hand detection)
        latestLandmarks = results.multiHandLandmarks[0]
        latestConfidence = results.multiHandedness?.[0]?.score || 0.5
        
        // Draw hand landmarks with different colors for each hand
        results.multiHandLandmarks.forEach((landmarks, index) => {
          const isLeftHand = results.multiHandedness[index]?.label === 'Left'
          const colors = isLeftHand 
            ? { connector: '#10b981', landmark: '#3b82f6' }
            : { connector: '#f59e0b', landmark: '#ef4444' }
          
          drawConnectors(ctx, landmarks, HAND_CONNECTIONS, { 
            color: colors.connector, 
            lineWidth: 3 
          })
          drawLandmarks(ctx, landmarks, { 
            color: colors.landmark, 
            lineWidth: 2, 
            radius: 5 
          })
        })
        
        // Clear error message when hands are detected
        if (errorMessage.value === 'No hand detected. Please position your hand in front of the camera.') {
          errorMessage.value = ''
        }
      } else {
        currentHandCount.value = 0
        handData.value = []
        latestLandmarks = null
        latestConfidence = 0.5
        
        // Show hint text when detecting
        if (isDetecting.value && ctx) {
          drawNoHandMessage(ctx, canvasRef.value)
        }
      }
    }
  }
}

const drawNoHandMessage = (ctx, canvas) => {
  ctx.fillStyle = 'rgba(0, 0, 0, 0.7)'
  ctx.fillRect(canvas.width / 2 - 150, canvas.height / 2 - 30, 300, 60)
  
  ctx.fillStyle = '#ffffff'
  ctx.font = 'bold 18px system-ui'
  ctx.textAlign = 'center'
  ctx.fillText('No hand detected', canvas.width / 2, canvas.height / 2 - 5)
  
  ctx.font = '14px system-ui'
  ctx.fillStyle = '#e5e7eb'
  ctx.fillText('Position your hand in front of the camera', canvas.width / 2, canvas.height / 2 + 15)
}

const drawConnectors = (ctx, landmarks, connections, style) => {
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

const drawLandmarks = (ctx, landmarks, style) => {
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
      isCameraReady.value = true
    }
  } catch (error) {
    console.error('Error starting camera:', error)
    errorMessage.value = 'Failed to access camera. Please check permissions.'
  }
}

const stopCamera = () => {
  if (camera) {
    camera.stop()
    isCameraReady.value = false
  }
}

const startDetection = async () => {
  await startCamera()
  isDetecting.value = true
  errorMessage.value = ''
  successMessage.value = ''
  startRealtimeDetection()
}

const stopDetection = () => {
  isDetecting.value = false
  stopRealtimeDetection()
  stopCamera()
  detectedGesture.value = ''
  confidence.value = 0
  realtimeGesture.value = ''
  realtimeConfidence.value = 0
}

const startRealtimeDetection = () => {
  if (isRealtimeDetecting.value) return
  
  isRealtimeDetecting.value = true
  realtimeDetectionInterval = setInterval(async () => {
    if (latestLandmarks && isDetecting.value) {
      await performDetection(latestLandmarks, latestConfidence, currentHandCount.value, handData.value, true)
    } else {
      realtimeGesture.value = ''
      realtimeConfidence.value = 0
    }
  }, 1500) // Detect every 1.5 seconds
}

const stopRealtimeDetection = () => {
  if (realtimeDetectionInterval) {
    clearInterval(realtimeDetectionInterval)
    realtimeDetectionInterval = null
  }
  isRealtimeDetecting.value = false
  realtimeGesture.value = ''
  realtimeConfidence.value = 0
}

const performDetection = async (landmarks, confidence, handCount, handDataArray, isRealtime = false) => {
  try {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
    
    if (!csrfToken) {
      console.error('CSRF token not found')
      return
    }
    
    const response = await fetch('/sign-language/detect', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken,
        'Accept': 'application/json'
      },
      body: JSON.stringify({
        landmarkData: landmarks,
        confidence: confidence,
        handCount: handCount,
        handData: handDataArray
      })
    })
    
    const result = await response.json()
    
    if (result.success) {
      if (result.gesture && result.meets_threshold) {
        // Gesture detected with sufficient confidence
        if (isRealtime) {
          realtimeGesture.value = result.gesture.label
          realtimeConfidence.value = result.confidence
          
          // Auto-add to sentence if confidence is high enough
          if (result.confidence > 0.75) {
            addToSentence(result.gesture.label, result.confidence, result.gesture.id)
          }
        } else {
          detectedGesture.value = result.gesture.label
          confidence.value = result.confidence
          
          // Store captured landmarks for visualization
          capturedLandmarks.value = landmarks
          showCapturedLandmarks.value = true
          
          addToSentence(result.gesture.label, result.confidence, result.gesture.id)
          
          setTimeout(() => {
            detectedGesture.value = ''
            confidence.value = 0
          }, 3000)
        }
        
        // Update detection history
        updateDetectionHistory(result.gesture.label, result.confidence)
      } else if (result.gesture && !result.meets_threshold) {
        // Gesture detected but confidence too low
        if (isRealtime) {
          realtimeGesture.value = `${result.gesture.label} (Low: ${(result.confidence * 100).toFixed(1)}%)`
          realtimeConfidence.value = result.confidence
          
          // Clear after 2 seconds
          setTimeout(() => {
            realtimeGesture.value = ''
            realtimeConfidence.value = 0
          }, 2000)
        } else {
          // Show low confidence warning
          errorMessage.value = `Gesture "${result.gesture.label}" detected but confidence too low (${(result.confidence * 100).toFixed(1)}%). Threshold: ${(result.threshold * 100)}%`
          setTimeout(() => {
            errorMessage.value = ''
          }, 3000)
        }
        
        // Still update detection history for tracking
        updateDetectionHistory(result.gesture.label, result.confidence)
      } else {
        // No gesture detected
        if (isRealtime) {
          realtimeGesture.value = ''
          realtimeConfidence.value = 0
        }
      }
    } else if (!result.success) {
      if (result.error === 'No trained gestures available') {
        errorMessage.value = 'No trained gestures available. Please train some gestures first.'
      } else {
        errorMessage.value = result.message || 'Detection failed. Please try again.'
      }
    }
  } catch (error) {
    console.error('Error in detection:', error)
    errorMessage.value = 'Network error. Please check your connection.'
  }
}

const detectGesture = async () => {
  if (!hands || !isDetecting.value) return
  
  if (latestLandmarks) {
    await performDetection(latestLandmarks, latestConfidence, currentHandCount.value, handData.value, false)
  } else {
    errorMessage.value = 'No hand detected. Please position your hand in front of the camera.'
    setTimeout(() => {
      errorMessage.value = ''
    }, 3000)
  }
}

const addToSentence = (gesture, confidence, gestureId) => {
  const timestamp = Date.now()
  
  // Add to detected gestures array
  detectedGestures.value.push({
    id: gestureId,
    label: gesture,
    confidence: confidence,
    timestamp: timestamp
  })
  
  // Add to sentence
  if (sentence.value) {
    sentence.value += ' ' + gesture
  } else {
    sentence.value = gesture
  }
  
  // Keep only last 20 gestures
  if (detectedGestures.value.length > 20) {
    detectedGestures.value = detectedGestures.value.slice(-20)
  }
}

const updateDetectionHistory = (gesture, confidence) => {
  detectionHistory.value.push({
    gesture,
    confidence,
    timestamp: Date.now()
  })
  
  // Keep only last 10 detections
  if (detectionHistory.value.length > 10) {
    detectionHistory.value = detectionHistory.value.slice(-10)
  }
  
  // Calculate average confidence
  if (detectionHistory.value.length > 0) {
    const total = detectionHistory.value.reduce((sum, item) => sum + item.confidence, 0)
    averageConfidence.value = total / detectionHistory.value.length
  }
}

const loadDetectionHistory = () => {
  const saved = localStorage.getItem('detectionHistory')
  if (saved) {
    try {
      detectionHistory.value = JSON.parse(saved)
      updateAverageConfidence()
    } catch (error) {
      console.error('Error loading detection history:', error)
    }
  }
}

const updateAverageConfidence = () => {
  if (detectionHistory.value.length > 0) {
    const total = detectionHistory.value.reduce((sum, item) => sum + item.confidence, 0)
    averageConfidence.value = total / detectionHistory.value.length
  }
}

const clearSentence = () => {
  sentence.value = ''
  detectedGestures.value = []
  successMessage.value = 'Sentence cleared!'
  setTimeout(() => {
    successMessage.value = ''
  }, 2000)
}

const copySentence = () => {
  if (!sentence.value) return
  
  navigator.clipboard.writeText(sentence.value).then(() => {
    successMessage.value = 'Sentence copied to clipboard!'
    setTimeout(() => {
      successMessage.value = ''
    }, 2000)
  }).catch(err => {
    console.error('Failed to copy sentence:', err)
    errorMessage.value = 'Failed to copy sentence'
    setTimeout(() => {
      errorMessage.value = ''
    }, 2000)
  })
}

const copyLandmarksData = () => {
  if (!capturedLandmarks.value) return
  
  const landmarksData = capturedLandmarks.value.map((landmark, index) => ({
    point: index,
    x: landmark.x.toFixed(3),
    y: landmark.y.toFixed(3),
    z: landmark.z.toFixed(3)
  }))
  
  const jsonData = JSON.stringify(landmarksData, null, 2)
  navigator.clipboard.writeText(jsonData).then(() => {
    successMessage.value = 'Landmarks data copied to clipboard!'
    setTimeout(() => {
      successMessage.value = ''
    }, 2000)
  }).catch(err => {
    console.error('Failed to copy landmarks data:', err)
  })
}

const drawCapturedLandmarks = () => {
  if (!landmarksCanvas.value || !capturedLandmarks.value) return
  
  const ctx = landmarksCanvas.value.getContext('2d')
  if (!ctx) return
  
  const canvas = landmarksCanvas.value
  
  // Clear canvas
  ctx.clearRect(0, 0, canvas.width, canvas.height)
  
  // Draw background
  ctx.fillStyle = '#000000'
  ctx.fillRect(0, 0, canvas.width, canvas.height)
  
  // Draw hand skeleton
  drawConnectors(ctx, capturedLandmarks.value, HAND_CONNECTIONS, { color: '#10b981', lineWidth: 3 })
  drawLandmarks(ctx, capturedLandmarks.value, { color: '#3b82f6', lineWidth: 2, radius: 6 })
  
  // Draw point numbers
  ctx.fillStyle = '#ffffff'
  ctx.font = '10px system-ui'
  ctx.textAlign = 'center'
  capturedLandmarks.value.forEach((landmark, index) => {
    const x = landmark.x * canvas.width
    const y = landmark.y * canvas.height
    ctx.fillText(index.toString(), x, y - 10)
  })
}

// Watch for modal open to draw landmarks
import { watch } from 'vue'

watch(showCapturedLandmarks, async (newValue) => {
  if (newValue && capturedLandmarks.value && landmarksCanvas.value) {
    await nextTick()
    drawCapturedLandmarks()
  }
})

const getConfidenceColor = (confidence) => {
  if (confidence >= 0.8) return 'text-green-600'
  if (confidence >= 0.6) return 'text-yellow-600'
  return 'text-red-600'
}

const getConfidenceBgColor = (confidence) => {
  if (confidence >= 0.8) return 'bg-green-100'
  if (confidence >= 0.6) return 'bg-yellow-100'
  return 'bg-red-100'
}
</script>

<template>
  <Head title="Sign Language Detection">
    <link rel="preconnect" href="https://rsms.me/" />
    <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
  </Head>
  
  <div class="min-h-screen bg-gradient-to-br from-blue-50 via-white to-purple-50">
    <!-- Navigation -->
    <nav class="border-b border-gray-200 bg-white/80 backdrop-blur-sm sticky top-0 z-50">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
          <div class="flex items-center">
            <div class="flex-shrink-0 flex items-center">
              <div class="w-8 h-8 bg-gradient-to-r from-blue-500 to-purple-600 rounded-lg mr-3"></div>
              <span class="text-xl font-bold bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">
                Signify
              </span>
            </div>
          </div>
          <div class="flex items-center space-x-4">
            <Link
              v-if="$page.props.auth.user"
              :href="dashboard()"
              class="text-gray-700 hover:text-gray-900 px-3 py-2 rounded-md text-sm font-medium transition-colors"
            >
              Dashboard
            </Link>
            <template v-else>
              <Link
                :href="login()"
                class="text-gray-700 hover:text-gray-900 px-3 py-2 rounded-md text-sm font-medium transition-colors"
              >
                Sign In
              </Link>
            </template>
          </div>
        </div>
      </div>
    </nav>

    <!-- Hero Section -->
    <div class="relative overflow-hidden">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="text-center">
          <h1 class="text-4xl md:text-6xl font-bold text-gray-900 mb-6">
            <span class="bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">
              Sign Language Detection
            </span>
            <br>
            <span class="text-3xl md:text-4xl text-gray-700">Powered by AI</span>
          </h1>
          <p class="text-xl text-gray-600 max-w-3xl mx-auto mb-8">
            Transform sign language gestures into text using advanced computer vision and machine learning. 
            Communicate seamlessly with real-time hand tracking technology supporting both single and dual-hand gestures.
          </p>
          
          <!-- Feature Pills -->
          <div class="flex flex-wrap justify-center gap-3 mb-12">
            <div class="inline-flex items-center px-4 py-2 bg-blue-100 text-blue-800 rounded-full text-sm font-medium">
              <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/>
                <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/>
              </svg>
              Real-time Detection
            </div>
            <div class="inline-flex items-center px-4 py-2 bg-purple-100 text-purple-800 rounded-full text-sm font-medium">
              <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M12.316 3.051a1 1 0 01.633 1.265l-4 12a1 1 0 11-1.898-.632l4-12a1 1 0 011.265-.633zM5.707 6.293a1 1 0 010 1.414L3.414 10l2.293 2.293a1 1 0 11-1.414 1.414l-3-3a1 1 0 010-1.414l3-3a1 1 0 011.414 0zm8.586 0a1 1 0 011.414 0l3 3a1 1 0 010 1.414l-3 3a1 1 0 11-1.414-1.414L16.586 10l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd"/>
              </svg>
              MediaPipe Hands
            </div>
            <div class="inline-flex items-center px-4 py-2 bg-green-100 text-green-800 rounded-full text-sm font-medium">
              <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
              </svg>
              Dual-Hand Support
            </div>
          </div>
        </div>

        <!-- Main Detection Card -->
        <div class="max-w-6xl mx-auto">
          <div class="grid gap-6 lg:grid-cols-3">
            <!-- Camera View -->
            <div class="lg:col-span-2">
              <Card class="bg-white/90 backdrop-blur-sm border-0 shadow-2xl">
                <CardHeader class="text-center pb-4">
                  <CardTitle class="text-2xl font-bold text-gray-900 mb-2">
                    🎯 Live Gesture Detection
                  </CardTitle>
                  <CardDescription class="text-gray-600">
                    Position your hand(s) in front of the camera and click "Start Detection"
                  </CardDescription>
                </CardHeader>
                <CardContent class="space-y-6">
                  <!-- Camera View -->
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
                      <div v-if="isDetecting" class="flex items-center space-x-2 bg-red-500 text-white px-3 py-1 rounded-full text-sm font-medium animate-pulse">
                        <div class="w-2 h-2 bg-white rounded-full animate-ping"></div>
                        <span>LIVE</span>
                      </div>
                      <div v-else-if="isCameraReady" class="flex items-center space-x-2 bg-gray-700 text-white px-3 py-1 rounded-full text-sm font-medium">
                        <div class="w-2 h-2 bg-green-400 rounded-full"></div>
                        <span>READY</span>
                      </div>
                    </div>

                    <!-- Hand Count Indicator -->
                    <div v-if="isDetecting && currentHandCount > 0" class="absolute top-4 left-4">
                      <div class="flex items-center space-x-2 bg-blue-500 text-white px-3 py-1 rounded-full text-sm font-medium">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                          <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM4.332 8.027a6.012 6.012 0 011.912-2.706C6.512 5.73 6.974 6 7.5 6A1.5 1.5 0 019 7.5V8a2 2 0 004 0 2 2 0 011.523-1.943A5.977 5.977 0 0116 10c0 .34-.028.675-.083 1H15a2 2 0 00-2 2v2.197A5.973 5.973 0 0110 16v-2a2 2 0 00-2-2 2 2 0 01-2-2 2 2 0 00-1.668-1.973z" clip-rule="evenodd"/>
                        </svg>
                        <span>{{ currentHandCount }} Hand{{ currentHandCount > 1 ? 's' : '' }}</span>
                      </div>
                    </div>

                    <!-- No Camera Placeholder -->
                    <div v-if="!isCameraReady && !isDetecting" class="absolute inset-0 flex items-center justify-center">
                      <div class="text-center text-white">
                        <svg class="w-16 h-16 mx-auto mb-4 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                        </svg>
                        <p class="text-lg font-medium">Camera Ready</p>
                        <p class="text-sm opacity-75">Click "Start Detection" to begin</p>
                      </div>
                    </div>
                  </div>
                  
                  <!-- Control Buttons -->
                  <div class="flex gap-3">
                    <Button
                      v-if="!isDetecting"
                      @click="startDetection"
                      size="lg"
                      class="flex-1 bg-gradient-to-r from-blue-500 to-purple-600 hover:from-blue-600 hover:to-purple-700 text-white font-medium transition-all transform hover:scale-105"
                    >
                      <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                      </svg>
                      Start Detection
                    </Button>
                    <Button
                      v-else
                      @click="stopDetection"
                      size="lg"
                      variant="destructive"
                      class="flex-1"
                    >
                      <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 10a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z"/>
                      </svg>
                      Stop Detection
                    </Button>
                    <Button
                      v-if="isDetecting"
                      @click="detectGesture"
                      size="lg"
                      variant="outline"
                      class="flex-1"
                    >
                      <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                      </svg>
                      Capture
                    </Button>
                  </div>
                  
                  <!-- Realtime Detection Status -->
                  <div v-if="isDetecting" class="bg-gradient-to-r from-orange-50 to-amber-50 border border-orange-200 p-4 rounded-lg">
                    <div class="flex items-center justify-between">
                      <div class="flex-1">
                        <div class="flex items-center mb-2">
                          <div class="w-2 h-2 bg-orange-500 rounded-full animate-pulse mr-2"></div>
                          <h3 class="font-semibold text-orange-800">🔴 Real-time Detection</h3>
                          <span class="ml-2 text-xs bg-orange-200 text-orange-800 px-2 py-1 rounded-full">Auto-mode</span>
                        </div>
                        <div v-if="realtimeGesture" class="space-y-1">
                          <p class="text-xl font-bold text-orange-700">{{ realtimeGesture }}</p>
                          <p class="text-sm text-orange-600">Confidence: {{ (realtimeConfidence * 100).toFixed(1) }}%</p>
                          <Progress :value="realtimeConfidence * 100" class="w-full h-2" />
                          <p v-if="realtimeConfidence > 0.75" class="text-xs text-green-600 font-medium">✓ Auto-added to sentence</p>
                        </div>
                        <p v-else class="text-orange-600 italic">Detecting gesture...</p>
                      </div>
                      <div class="text-3xl">🤚</div>
                    </div>
                  </div>

                  <!-- Messages -->
                  <Alert v-if="errorMessage" variant="destructive" class="mb-4">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                    <AlertDescription>{{ errorMessage }}</AlertDescription>
                  </Alert>
                  
                  <Alert v-if="successMessage" class="mb-4">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <AlertDescription>{{ successMessage }}</AlertDescription>
                  </Alert>
                  
                  <!-- Detected Gesture -->
                  <div v-if="detectedGesture" class="bg-gradient-to-r from-green-50 to-emerald-50 border border-green-200 p-4 rounded-lg">
                    <div class="flex items-center justify-between">
                      <div>
                        <h3 class="font-semibold text-green-800 mb-1">✨ Gesture Detected!</h3>
                        <p class="text-2xl font-bold text-green-700">{{ detectedGesture }}</p>
                        <p class="text-sm text-green-600 mt-1">Confidence: {{ (confidence * 100).toFixed(1) }}%</p>
                        <Progress :value="confidence * 100" class="w-full h-2 mt-2" />
                      </div>
                      <div class="text-4xl">👋</div>
                    </div>
                  </div>
                </CardContent>
              </Card>
            </div>

            <!-- Side Panel -->
            <div class="space-y-6">
              <!-- Sentence Formation -->
              <Card>
                <CardHeader>
                  <CardTitle class="text-lg">📝 Formed Sentence</CardTitle>
                  <CardDescription>
                    Gestures are automatically added to form sentences
                  </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                  <Textarea
                    v-model="sentence"
                    placeholder="No gestures captured yet..."
                    class="min-h-[100px] resize-none bg-white border-gray-100 text-gray-800 text-lg"
                    readonly
                  />
                  <div class="flex gap-2">
                    <Button
                      v-if="sentence"
                      @click="copySentence"
                      variant="outline"
                      size="sm"
                      class="flex-1"
                    >
                      <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                      </svg>
                      Copy
                    </Button>
                    <Button
                      v-if="sentence"
                      @click="clearSentence"
                      variant="outline"
                      size="sm"
                      class="flex-1"
                    >
                      Clear
                    </Button>
                  </div>
                  <div class="text-xs text-gray-500 space-y-1">
                    <div>💡 Auto-mode: Gestures with confidence > 75% are added automatically</div>
                    <div>📋 {{ detectedGestures.length }} gestures captured</div>
                  </div>
                </CardContent>
              </Card>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Captured Landmarks Modal -->
    <div v-if="showCapturedLandmarks && capturedLandmarks" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-auto">
        <div class="p-6 border-b border-gray-200">
          <div class="flex items-center justify-between">
            <h3 class="text-xl font-bold text-gray-900">📊 Captured Hand Landmarks</h3>
            <Button
              @click="showCapturedLandmarks = false"
              variant="ghost"
              size="sm"
              class="text-gray-500 hover:text-gray-700"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </Button>
          </div>
        </div>
        
        <div class="p-6 space-y-6">
          <!-- Landmarks Visualization -->
          <div class="bg-gradient-to-br from-gray-900 to-gray-800 rounded-xl p-4">
            <h4 class="text-white font-semibold mb-3">Hand Skeleton</h4>
            <div class="relative aspect-square bg-black rounded-lg overflow-hidden">
              <canvas
                ref="landmarksCanvas"
                class="w-full h-full"
                width="400"
                height="400"
              ></canvas>
            </div>
          </div>
          
          <!-- Landmarks Data -->
          <div class="bg-gray-50 rounded-xl p-4">
            <h4 class="font-semibold text-gray-900 mb-3">📍 Landmark Coordinates (21 Points)</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 max-h-60 overflow-y-auto">
              <div
                v-for="(landmark, index) in capturedLandmarks"
                :key="index"
                class="bg-white p-3 rounded-lg border border-gray-200 text-xs"
              >
                <div class="font-semibold text-gray-700 mb-1">Point {{ index }}</div>
                <div class="space-y-1 text-gray-600">
                  <div>X: {{ landmark.x.toFixed(3) }}</div>
                  <div>Y: {{ landmark.y.toFixed(3) }}</div>
                  <div>Z: {{ landmark.z.toFixed(3) }}</div>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Action Buttons -->
          <div class="flex gap-3 pt-4 border-t border-gray-200">
            <Button
              @click="showCapturedLandmarks = false"
              class="flex-1"
            >
              Close
            </Button>
            <Button
              @click="copyLandmarksData"
              variant="outline"
              class="flex-1"
            >
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
              </svg>
              Copy Data
            </Button>
          </div>
        </div>
      </div>
    </div>

    <!-- Footer -->
    <footer class="bg-white/80 backdrop-blur-sm border-t border-gray-200 mt-16">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="text-center text-gray-600">
          <p class="mb-2">Built with ❤️ using Laravel, Vue.js, and MediaPipe</p>
          <p class="text-sm">© 2024 Signify. Empowering communication through technology.</p>
        </div>
      </div>
    </footer>
  </div>
</template>
