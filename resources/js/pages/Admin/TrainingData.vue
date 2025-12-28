<script setup>
import { ref, onMounted, onUnmounted, computed, watch } from 'vue'
import { debounce } from 'lodash'
import { Head, router } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Select } from '@/components/ui/select'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Separator } from '@/components/ui/separator'
import { Progress } from '@/components/ui/progress'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog'
import { Checkbox } from '@/components/ui/checkbox'
import { Skeleton } from '@/components/ui/skeleton'
import AppLayout from '@/layouts/AppLayout.vue'


const props = defineProps({
  gestures: {
    type: Array,
    default: () => []
  },
  trainingData: {
    type: Object,
    default: () => ({ data: [], meta: {}, links: [] })
  },
  filters: {
    type: Object,
    default: () => ({})
  }
})

// Camera and capture refs
const videoRef = ref(null)
const canvasRef = ref(null)
const isCapturing = ref(false)
const isCameraReady = ref(false)
const errorMessage = ref('')
const successMessage = ref('')

// Form refs
const selectedGestureId = ref('')
const captureNotes = ref('')
const capturedLandmarks = ref(null)
const currentHandCount = ref(0)
const handData = ref([])

// Data management refs
// const trainingDataList = ref([]) // Removed, using props directly
const loading = ref(false)
const searchQuery = ref(props.filters.search || '')
const selectedGesture = ref(props.filters.gesture_id || '')
const selectedHandCount = ref(props.filters.hand_count || '')
const selectedQuality = ref(props.filters.quality || '')

// Watchers for filters
const updateFilters = debounce(() => {
  router.get('/admin/training-data', {
    search: searchQuery.value,
    gesture_id: selectedGesture.value,
    hand_count: selectedHandCount.value,
    quality: selectedQuality.value
  }, {
    preserveState: true,
    preserveScroll: true,
    replace: true
  })
}, 300)

watch([searchQuery, selectedGesture, selectedHandCount, selectedQuality], () => {
    updateFilters()
})

// Statistics refs
const statistics = ref(null)
const loadingStats = ref(false)

// CRUD operations refs
const editingItem = ref(null)
const showEditDialog = ref(false)
const showDeleteConfirm = ref(false)
const itemToDelete = ref(null)
const deletingId = ref(null)
const selectedItems = ref(new Set())
const showBulkActions = ref(false)

// Export refs
const showExportDialog = ref(false)
const exportFormat = ref('json')
const exportGesture = ref('')
const exportHandCount = ref('')

// MediaPipe instances
let hands = null
let camera = null
let latestLandmarks = null
let latestConfidence = 0.5

// Computed properties
// Computed properties
const hasSelectedItems = computed(() => selectedItems.value.size > 0)

const gestureOptions = computed(() => {
  return props.gestures.map(gesture => ({
    value: gesture.id.toString(),
    label: gesture.label
  }))
})


// Pagination Logic
const paginationLinks = computed(() => {
  const links = props.trainingData.links
  if (!links || links.length === 0) return []
  
  // If we have very few pages (e.g. up to 5 + 2 prev/next = 7 items), show all
  if (links.length <= 7) return links

  const windowSize = 3
  
  const prev = links[0]
  const next = links[links.length - 1]
  
  const dataLinks = links.slice(1, -1)
  const activeIndex = dataLinks.findIndex(l => l.active)
  
  let start = activeIndex - 1
  if (start < 0) start = 0
  
  let end = start + windowSize
  if (end > dataLinks.length) {
    end = dataLinks.length
    start = Math.max(0, end - windowSize)
  }
  
  const visibleSubset = dataLinks.slice(start, end)
  
  return [prev, ...visibleSubset, next]
})

onMounted(async () => {
  await initializeMediaPipe()
  loadStatistics()
})

onUnmounted(() => {
  stopCapture()
  if (camera) {
    camera.stop()
  }
})

// MediaPipe functions
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
    errorMessage.value = 'Gagal menginisialisasi deteksi tangan. Periksa izin kamera Anda.'
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

        latestLandmarks = results.multiHandLandmarks[0]
        latestConfidence = results.multiHandedness?.[0]?.score || 0.5

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
      } else {
        currentHandCount.value = 0
        handData.value = []
        latestLandmarks = null
        latestConfidence = 0.5
      }
    }
  }
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
  [0, 1], [1, 2], [2, 3], [3, 4],
  [0, 5], [5, 6], [6, 7], [7, 8],
  [5, 9], [9, 10], [10, 11], [11, 12],
  [9, 13], [13, 14], [14, 15], [15, 16],
  [13, 17], [17, 18], [18, 19], [19, 20],
  [0, 17]
]

// Camera control functions
const startCamera = async () => {
  try {
    if (camera) {
      await camera.start()
      isCameraReady.value = true
    }
  } catch (error) {
    console.error('Error starting camera:', error)
    errorMessage.value = 'Gagal mengakses kamera. Periksa izin Anda.'
  }
}

const stopCamera = () => {
  if (camera) {
    camera.stop()
    isCameraReady.value = false
  }
}

const startCapture = async () => {
  await startCamera()
  isCapturing.value = true
  errorMessage.value = ''
  successMessage.value = ''
}

const stopCapture = () => {
  isCapturing.value = false
  stopCamera()
  capturedLandmarks.value = null
  currentHandCount.value = 0
  handData.value = []
}

// Data management functions 
// (loadTrainingData removed as it is now handled by Inertia props)

const loadStatistics = async () => {
  loadingStats.value = true
  try {
    const response = await fetch('/admin/training-data/statistics')
    const result = await response.json()

    if (result.success) {
      statistics.value = result.stats
    }
  } catch (error) {
    console.error('Error loading statistics:', error)
  } finally {
    loadingStats.value = false
  }
}

// CRUD operations
const captureTrainingData = async () => {
  if (!selectedGestureId.value) {
    errorMessage.value = 'Pilih gesture terlebih dahulu'
    return
  }

  if (!latestLandmarks) {
    errorMessage.value = 'Tidak ada tangan terdeteksi. Posisikan tangan Anda di depan kamera.'
    return
  }

  const selectedGesture = props.gestures.find(g => g.id === parseInt(selectedGestureId.value))
  if (!selectedGesture) {
    errorMessage.value = 'Gesture yang dipilih tidak valid'
    return
  }

  if (selectedGesture.supports_dual_hand && currentHandCount.value < 2) {
    errorMessage.value = 'Gesture ini membutuhkan dua tangan. Tunjukkan kedua tangan.'
    return
  }

  // if (!selectedGesture.supports_dual_hand && currentHandCount.value > 1) {
  //   errorMessage.value = 'Gesture ini hanya membutuhkan satu tangan. Tunjukkan hanya satu tangan.'
  //   return
  // }

  try {
    router.post('/admin/training-data', {
      gesture_id: selectedGestureId.value,
      landmark_data: latestLandmarks,
      confidence_score: latestConfidence,
      hand_count: currentHandCount.value,
      hand_data: handData.value,
      notes: captureNotes.value,
      metadata: {
        captured_at: new Date().toISOString(),
        hand_count: currentHandCount.value,
        gesture_type: selectedGesture.supports_dual_hand ? 'dual_hand' : 'single_hand'
      }
    }, {
      onSuccess: (page) => {
        successMessage.value = `Data training untuk "${page.props.flash?.success || 'gesture'}" berhasil disimpan!`
        capturedLandmarks.value = latestLandmarks
        captureNotes.value = ''
        loadStatistics()
        // loadTrainingData() // Removed

        setTimeout(() => {
          successMessage.value = ''
        }, 3000)
      },
      onError: (errors) => {
        console.error('Validation errors:', errors)
        errorMessage.value = Object.values(errors).join(', ') || 'Gagal menyimpan data training'
      },
      preserveState: true,
      preserveScroll: true
    })
  } catch (error) {
    console.error('Error saving training data:', error)
    errorMessage.value = 'Kesalahan jaringan. Periksa koneksi Anda.'
  }
}

const editTrainingData = (item) => {
  editingItem.value = { ...item }
  showEditDialog.value = true
}

const updateTrainingData = async () => {
  if (!editingItem.value) return

  try {
    const response = await fetch(`/admin/training-data/${editingItem.value.id}`, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
      },
      body: JSON.stringify({
        notes: editingItem.value.notes,
        is_validated: editingItem.value.is_validated
      })
    })

    const result = await response.json()

    if (result.success) {
      successMessage.value = 'Data training berhasil diperbarui!'
      // loadTrainingData() // Removed
      showEditDialog.value = false
      editingItem.value = null

      setTimeout(() => {
        successMessage.value = ''
      }, 3000)
    } else {
      errorMessage.value = result.message || 'Gagal memperbarui data training'
    }
  } catch (error) {
    console.error('Error updating training data:', error)
    errorMessage.value = 'Kesalahan jaringan. Coba lagi.'
  }
}

const confirmDelete = (item) => {
  itemToDelete.value = item
  showDeleteConfirm.value = true
}

const cancelDelete = () => {
  itemToDelete.value = null
  showDeleteConfirm.value = false
  deletingId.value = null
}

const deleteTrainingData = async (id) => {
  if (!id) return

  deletingId.value = id

  try {
    const response = await fetch(`/admin/training-data/${id}`, {
      method: 'DELETE',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
      }
    })

    const result = await response.json()

    if (result.success) {
      successMessage.value = result.message || 'Data training berhasil dihapus!'
      // loadTrainingData() // Removed - Inertia reload might be needed or manual splice if we want to avoid reload
      router.reload({ only: ['trainingData', 'gestures'] }) // Reload data using Inertia
      loadStatistics()

      setTimeout(() => {
        successMessage.value = ''
      }, 3000)
    } else {
      errorMessage.value = result.message || 'Gagal menghapus data training'
    }
  } catch (error) {
    console.error('Error deleting training data:', error)
    errorMessage.value = 'Kesalahan jaringan. Coba lagi.'
  } finally {
    deletingId.value = null
    cancelDelete()
  }
}

// Bulk operations
const toggleSelectAll = () => {
  if (selectedItems.value.size === props.trainingData.data.length) {
    selectedItems.value.clear()
  } else {
    props.trainingData.data.forEach(item => {
      selectedItems.value.add(item.id)
    })
  }
}

const toggleSelectItem = (id) => {
  if (selectedItems.value.has(id)) {
    selectedItems.value.delete(id)
  } else {
    selectedItems.value.add(id)
  }
}

const bulkDelete = async () => {
  if (selectedItems.value.size === 0) return

  try {
    const response = await fetch('/admin/training-data/bulk-delete', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
      },
      body: JSON.stringify({
        ids: Array.from(selectedItems.value)
      })
    })

    const result = await response.json()

    if (result.success) {
      successMessage.value = `${selectedItems.value.size} item berhasil dihapus!`
      selectedItems.value.clear()
      router.reload({ only: ['trainingData', 'gestures'] })
      loadStatistics()

      setTimeout(() => {
        successMessage.value = ''
      }, 3000)
    } else {
      errorMessage.value = result.message || 'Gagal menghapus item'
    }
  } catch (error) {
    console.error('Error bulk deleting:', error)
    errorMessage.value = 'Kesalahan jaringan. Coba lagi.'
  }
}

// Pagination functions removed - handled by Link or router.visit in template


// Export functions
const exportData = async () => {
  try {
    const params = new URLSearchParams()
    if (exportFormat.value) params.append('format', exportFormat.value)
    if (exportGesture.value) params.append('gesture_id', exportGesture.value)
    if (exportHandCount.value) params.append('hand_count', exportHandCount.value)

    const response = await fetch(`/admin/training-data/export?${params}`)

    if (exportFormat.value === 'csv') {
      const blob = await response.blob()
      const url = window.URL.createObjectURL(blob)
      const a = document.createElement('a')
      a.href = url
      a.download = `training_data_${new Date().toISOString().split('T')[0]}.csv`
      a.click()
      window.URL.revokeObjectURL(url)
    } else {
      const result = await response.json()
      const blob = new Blob([JSON.stringify(result, null, 2)], { type: 'application/json' })
      const url = window.URL.createObjectURL(blob)
      const a = document.createElement('a')
      a.href = url
      a.download = `training_data_${new Date().toISOString().split('T')[0]}.json`
      a.click()
      window.URL.revokeObjectURL(url)
    }

    showExportDialog.value = false
    successMessage.value = 'Data berhasil diekspor!'

    setTimeout(() => {
      successMessage.value = ''
    }, 3000)
  } catch (error) {
    console.error('Error exporting data:', error)
    errorMessage.value = 'Gagal mengekspor data'
  }
}

// Utility functions
const getConfidenceColor = (confidence) => {
  if (confidence >= 0.8) return 'text-green-600'
  if (confidence >= 0.6) return 'text-yellow-600'
  return 'text-red-600'
}

const getConfidenceBgColor = (confidence) => {
  if (confidence >= 0.8) return 'bg-green-100 text-green-800'
  if (confidence >= 0.6) return 'bg-yellow-100 text-yellow-800'
  return 'bg-red-100 text-red-800'
}

const getQualityBadge = (confidence) => {
  if (confidence >= 0.8) return { label: 'Tinggi', variant: 'default' }
  if (confidence >= 0.6) return { label: 'Sedang', variant: 'secondary' }
  return { label: 'Rendah', variant: 'destructive' }
}

const formatDate = (dateString) => {
  return new Date(dateString).toLocaleString('id-ID', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

const clearFilters = () => {
  searchQuery.value = ''
  selectedGesture.value = ''
  selectedHandCount.value = ''
  selectedQuality.value = ''
  // No need to reset currentPage manually, Inertia handles it or it defaults to 1 on new search if not preserved
}
</script>

<template>
  <Head title="Manajemen Data Training" />

  <AppLayout>
    <div class="min-h-screen bg-background">
      <!-- Header -->
      <div class="border-b">
        <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div class="flex items-center justify-between">
            <div>
              <h1 class="text-xl font-bold tracking-tight">Manajemen Data Training</h1>
              <p class="mt-2 text-muted-foreground">Tangkap, kelola, dan ekspor data training gesture</p>
            </div>
            <div class="flex items-center gap-3">
              <Button @click="showExportDialog = true" variant="outline">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                Export Data
              </Button>
              <Button @click="loadStatistics" :disabled="loadingStats">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                Refresh
              </Button>
            </div>
          </div>
        </div>
      </div>

      <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="grid gap-8 lg:grid-cols-3">
          <!-- Capture Section -->
          <div class="lg:col-span-1">
            <Card class="sticky top-6">
              <CardHeader class="bg-primary text-primary-foreground rounded-t-lg">
                <CardTitle class="flex items-center text-primary-foreground">
                  <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                  </svg>
                  Capture Data Training
                </CardTitle>
                <CardDescription class="text-primary-foreground/80">
                  Rekam gesture untuk melatih model AI
                </CardDescription>
              </CardHeader>
              <CardContent class="space-y-6 p-6">
                <!-- Gesture Selection -->
                <div class="space-y-2">
                  <Label for="gesture" class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">
                    Pilih Gesture
                  </Label>
                  <Select v-model="selectedGestureId" :items="gestureOptions"
                    placeholder="Pilih gesture untuk dilatih" />
                </div>

                <!-- Camera View -->
                <div class="relative rounded-lg overflow-hidden bg-muted border-2 border-border">
                  <div class="relative aspect-square">
                    <video ref="videoRef" class="absolute inset-0 w-full h-full object-cover" autoplay muted></video>
                    <canvas ref="canvasRef" class="absolute inset-0 w-full h-full" width="480" height="480"></canvas>
                  </div>

                  <!-- Status Overlay -->
                  <div class="absolute top-4 right-4">
                    <div v-if="isCapturing"
                      class="flex items-center gap-2 bg-destructive text-destructive-foreground px-3 py-1 rounded-full text-sm font-medium animate-pulse shadow-lg">
                      <div class="w-2 h-2 bg-destructive-foreground rounded-full animate-ping"></div>
                      <span>REKAM</span>
                    </div>
                    <div v-else-if="isCameraReady"
                      class="flex items-center gap-2 bg-green-600 text-white px-3 py-1 rounded-full text-sm font-medium shadow-lg">
                      <div class="w-2 h-2 bg-white rounded-full"></div>
                      <span>SIAP</span>
                    </div>
                  </div>

                  <!-- Hand Count Indicator -->
                  <div v-if="isCapturing && currentHandCount > 0" class="absolute top-4 left-4">
                    <div class="flex items-center gap-2 bg-primary text-primary-foreground px-3 py-1 rounded-full text-sm font-medium shadow-lg">
                      <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd"
                          d="M10 18a8 8 0 100-16 8 8 0 000 16zM4.332 8.027a6.012 6.012 0 011.912-2.706C6.512 5.73 6.974 6 7.5 6A1.5 1.5 0 019 7.5V8a2 2 0 004 0 2 2 0 011.523-1.943A5.977 5.977 0 0116 10c0 .34-.028.675-.083 1H15a2 2 0 00-2 2v2.197A5.973 5.973 0 0110 16v-2a2 2 0 00-2-2 2 2 0 01-2-2 2 2 0 00-1.668-1.973z"
                          clip-rule="evenodd" />
                      </svg>
                      <span>{{ currentHandCount }} Tangan</span>
                    </div>
                  </div>

                  <!-- No Camera Placeholder -->
                  <div v-if="!isCameraReady && !isCapturing"
                    class="absolute inset-0 flex items-center justify-center">
                    <div class="text-center text-muted-foreground">
                      <svg class="w-16 h-16 mx-auto mb-4 opacity-50" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                      </svg>
                      <p class="text-lg font-medium">Kamera Siap</p>
                      <p class="text-sm opacity-75">Klik "Mulai Capture" untuk memulai</p>
                    </div>
                  </div>
                </div>

                <!-- Control Buttons -->
                <div class="flex gap-3">
                  <Button v-if="!isCapturing" @click="startCapture" size="lg" class="flex-1">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Mulai Capture
                  </Button>
                  <Button v-else @click="stopCapture" size="lg" variant="destructive" class="flex-1">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 10a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z" />
                    </svg>
                    Stop Capture
                  </Button>
                  <Button v-if="isCapturing && selectedGestureId" @click="captureTrainingData" size="lg"
                    variant="outline" class="flex-1">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    Simpan
                  </Button>
                </div>

                <!-- Notes -->
                <div class="space-y-2">
                  <Label for="notes" class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">
                    Catatan (Opsional)
                  </Label>
                  <Textarea id="notes" v-model="captureNotes"
                    placeholder="Tambahkan catatan tentang sample training ini..."
                    class="resize-none" rows="3" />
                </div>

                <!-- Messages -->
                <Alert v-if="errorMessage" variant="destructive">
                  <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd"
                      d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                      clip-rule="evenodd" />
                  </svg>
                  <AlertDescription>{{ errorMessage }}</AlertDescription>
                </Alert>

                <Alert v-if="successMessage">
                  <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd"
                      d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                      clip-rule="evenodd" />
                  </svg>
                  <AlertDescription>{{ successMessage }}</AlertDescription>
                </Alert>
              </CardContent>
            </Card>
          </div>

          <!-- Data Management Section -->
          <div class="lg:col-span-2 space-y-6">
            <!-- Filters -->
            <Card>
              <CardHeader class="bg-secondary text-secondary-foreground rounded-t-lg">
                <CardTitle class="flex items-center justify-between text-secondary-foreground">
                  <span class="flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                    </svg>
                    Filter & Pencarian
                  </span>
                  <Button variant="outline" size="sm" @click="clearFilters">
                    Reset
                  </Button>
                </CardTitle>
              </CardHeader>
              <CardContent class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                  <div class="space-y-2">
                    <Label class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">
                      Pencarian
                    </Label>
                    <Input v-model="searchQuery" placeholder="Cari gesture atau catatan..." />
                  </div>
                  <div class="space-y-2">
                    <Label class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">
                      Gesture
                    </Label>
                    <Select v-model="selectedGesture"
                      :items="[{ value: '', label: 'Semua Gesture' }, ...gestureOptions]" placeholder="Semua Gesture" />
                  </div>
                  <div class="space-y-2">
                    <Label class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">
                      Jumlah Tangan
                    </Label>
                    <Select v-model="selectedHandCount" :items="[
                      { value: '', label: 'Semua' },
                      { value: '1', label: '1 Tangan' },
                      { value: '2', label: '2 Tangan' }
                    ]" placeholder="Semua" />
                  </div>
                  <div class="space-y-2">
                    <Label class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">
                      Kualitas
                    </Label>
                    <Select v-model="selectedQuality" :items="[
                      { value: '', label: 'Semua' },
                      { value: 'high', label: 'Tinggi (≥80%)' },
                      { value: 'medium', label: 'Sedang (60-79%)' },
                      { value: 'low', label: 'Rendah (<60%)' }
                    ]" placeholder="Semua" />
                  </div>
                </div>
              </CardContent>
            </Card>

            <!-- Data Table -->
            <Card>
              <CardHeader class="bg-secondary text-secondary-foreground rounded-t-lg">
                <CardTitle class="flex items-center justify-between text-secondary-foreground">
                  <span class="flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                    Data Training ({{ props.trainingData.total }} items)
                  </span>
                  <div v-if="hasSelectedItems" class="flex items-center gap-2">
                    <span class="text-sm text-secondary-foreground/80">{{ selectedItems.size }} dipilih</span>
                    <Button variant="outline" size="sm" @click="bulkDelete" class="border-destructive text-destructive hover:bg-destructive hover:text-destructive-foreground">
                      <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                      </svg>
                      Hapus
                    </Button>
                  </div>
                </CardTitle>
              </CardHeader>
              <CardContent class="p-0">
                <div v-if="loading" class="p-6 space-y-4">
                  <Skeleton class="h-16 w-full" />
                  <Skeleton class="h-16 w-full" />
                  <Skeleton class="h-16 w-full" />
                </div>

                <div v-else-if="props.trainingData.data.length === 0" class="text-center py-12">
                  <svg class="w-16 h-16 mx-auto mb-4 text-muted-foreground" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                  </svg>
                  <p class="text-lg font-medium text-muted-foreground">Tidak ada data training</p>
                  <p class="text-muted-foreground">Coba ubah filter atau capture sample baru</p>
                </div>

                <div v-else class="overflow-x-auto">
                  <!-- Table Header -->
                  <div class="bg-muted/50 border-b">
                    <div class="grid grid-cols-12 gap-4 px-6 py-3 text-xs font-medium text-muted-foreground uppercase tracking-wider">
                      <div class="col-span-1 flex items-center">
                         <Checkbox :checked="selectedItems.size === props.trainingData.data.length && props.trainingData.data.length > 0"
                          @update:checked="toggleSelectAll" />
                      </div>
                      <div class="col-span-3">Gesture</div>
                      <div class="col-span-2 text-center">Kualitas</div>
                      <div class="col-span-1 text-center">Tangan</div>
                      <div class="col-span-2 text-center">Tanggal</div>
                      <div class="col-span-2 text-center">Status</div>
                      <div class="col-span-1 text-center">Aksi</div>
                    </div>
                  </div>

                  <!-- Table Body -->
                  <div class="divide-y">
                    <div v-for="data in props.trainingData.data" :key="data.id"
                      class="grid grid-cols-12 gap-4 px-6 py-4 hover:bg-muted/50 transition-colors items-center">
                      <div class="col-span-1">
                        <Checkbox :checked="selectedItems.has(data.id)"
                          @update:checked="() => toggleSelectItem(data.id)" />
                      </div>
                      <div class="col-span-3">
                        <div class="flex items-center gap-2">
                          <h4 class="font-semibold text-foreground">{{ data.gesture?.label }}</h4>
                          <Badge v-if="data.hand_count > 1" variant="outline" class="text-xs">
                            2T
                          </Badge>
                        </div>
                        <p v-if="data.notes" class="text-sm text-muted-foreground truncate mt-1">{{ data.notes }}</p>
                      </div>
                      <div class="col-span-2 text-center">
                        <Badge :class="getConfidenceBgColor(data.confidence_score)" class="text-xs font-medium">
                          {{ (data.confidence_score * 100).toFixed(1) }}%
                        </Badge>
                      </div>
                      <div class="col-span-1 text-center">
                        <span class="text-sm font-medium text-foreground">{{ data.hand_count }}</span>
                      </div>
                      <div class="col-span-2 text-center">
                        <span class="text-xs text-muted-foreground">{{ formatDate(data.created_at) }}</span>
                      </div>
                      <div class="col-span-2 text-center">
                        <Badge :variant="data.is_validated ? 'default' : 'secondary'" class="text-xs">
                          {{ data.is_validated ? 'Valid' : 'Pending' }}
                        </Badge>
                      </div>
                      <div class="col-span-1 text-center">
                        <div class="flex items-center justify-center gap-1">
                          <Button @click="editTrainingData(data)" variant="ghost" size="sm"
                            class="h-8 w-8 p-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                          </Button>
                          <Button @click="confirmDelete(data)" variant="ghost" size="sm"
                            class="h-8 w-8 p-0 text-destructive hover:text-destructive hover:bg-destructive/10"
                            :disabled="deletingId === data.id">
                            <svg v-if="deletingId === data.id" class="w-4 h-4 animate-spin" fill="none"
                              stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                            <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                          </Button>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Pagination -->
                <div v-if="props.trainingData.links && props.trainingData.links.length > 3" class="border-t bg-card px-6 py-4">
                  <div class="flex items-center justify-between">
                    <div class="text-sm text-muted-foreground">
                      Menampilkan {{ props.trainingData.from }} hingga {{ props.trainingData.to }} dari {{ props.trainingData.total }} data
                    </div>
                    <div class="flex items-center gap-2">
                         <div class="flex items-center space-x-2">
                          <Button
                            v-for="(link, index) in paginationLinks"
                            :key="index"
                            :variant="link.active ? 'default' : 'outline'"
                            size="sm"
                            :disabled="!link.url"
                            @click="link.url && router.visit(link.url)"
                            :class="{'opacity-50 cursor-not-allowed': !link.url, 'bg-primary text-primary-foreground': link.active}"
                            v-html="link.label"
                          />
                        </div>
                    </div>
                  </div>
                </div>
              </CardContent>
            </Card>
          </div>
        </div>
      </div>

      <!-- Statistics Cards -->
      <div v-if="statistics" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-8">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          <Card class="hover:shadow-lg transition-all duration-300 hover:scale-105">
            <CardContent class="p-6">
              <div class="flex items-center">
                <div class="flex-shrink-0 bg-primary rounded-lg p-3">
                  <svg class="w-6 h-6 text-primary-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z" />
                  </svg>
                </div>
                <div class="ml-4">
                  <p class="text-sm font-medium text-muted-foreground">Total Gesture</p>
                  <p class="text-2xl font-bold text-foreground">{{ statistics.total_gestures }}</p>
                </div>
              </div>
            </CardContent>
          </Card>

          <Card class="hover:shadow-lg transition-all duration-300 hover:scale-105">
            <CardContent class="p-6">
              <div class="flex items-center">
                <div class="flex-shrink-0 bg-green-600 rounded-lg p-3">
                  <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </div>
                <div class="ml-4">
                  <p class="text-sm font-medium text-muted-foreground">Data Training</p>
                  <p class="text-2xl font-bold text-foreground">{{ statistics.total_training_data }}</p>
                </div>
              </div>
            </CardContent>
          </Card>

          <Card class="hover:shadow-lg transition-all duration-300 hover:scale-105">
            <CardContent class="p-6">
              <div class="flex items-center">
                <div class="flex-shrink-0 bg-purple-600 rounded-lg p-3">
                  <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M13 10V3L4 14h7v7l9-11h-7z" />
                  </svg>
                </div>
                <div class="ml-4">
                  <p class="text-sm font-medium text-muted-foreground">Rata-rata Confidence</p>
                  <p class="text-2xl font-bold text-foreground">{{ (statistics.average_confidence * 100).toFixed(1) }}%
                  </p>
                </div>
              </div>
            </CardContent>
          </Card>

          <Card class="hover:shadow-lg transition-all duration-300 hover:scale-105">
            <CardContent class="p-6">
              <div class="flex items-center">
                <div class="flex-shrink-0 bg-orange-600 rounded-lg p-3">
                  <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </div>
                <div class="ml-4">
                  <p class="text-sm font-medium text-muted-foreground">Capture Terbaru</p>
                  <p class="text-2xl font-bold text-foreground">{{ statistics.recent_captures }}</p>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>

      <!-- Edit Dialog -->
      <Dialog v-model:open="showEditDialog">
        <DialogContent class="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>Edit Data Training</DialogTitle>
            <DialogDescription>
              Update catatan dan status validasi untuk sample training ini.
            </DialogDescription>
          </DialogHeader>
          <div v-if="editingItem" class="space-y-4 py-4">
            <div class="space-y-2">
              <Label class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">
                Gesture
              </Label>
              <Input :value="editingItem.gesture?.label" disabled class="bg-muted" />
            </div>
            <div class="space-y-2">
              <Label for="edit-notes" class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">
                Catatan
              </Label>
              <Textarea id="edit-notes" v-model="editingItem.notes"
                placeholder="Tambahkan catatan tentang sample training ini..." rows="3" />
            </div>
            <div class="flex items-center space-x-2">
              <Checkbox id="edit-validated" v-model:checked="editingItem.is_validated" />
              <Label for="edit-validated" class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">
                Tervalidasi untuk training
              </Label>
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" @click="showEditDialog = false">
              Batal
            </Button>
            <Button @click="updateTrainingData">
              Simpan Perubahan
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <!-- Delete Confirmation Dialog -->
      <Dialog v-model:open="showDeleteConfirm">
        <DialogContent class="sm:max-w-md" @keydown.enter="deleteTrainingData(itemToDelete?.id)">
          <DialogHeader>
            <DialogTitle>Hapus Data Training</DialogTitle>
            <DialogDescription>
              Apakah Anda yakin ingin menghapus sample training ini? Tindakan ini tidak dapat dibatalkan.
            </DialogDescription>
          </DialogHeader>
          <div v-if="itemToDelete" class="mb-4 p-4 bg-muted rounded-lg">
            <p class="text-sm font-medium text-foreground">{{ itemToDelete.gesture?.label }}</p>
            <p v-if="itemToDelete.notes" class="text-sm text-muted-foreground mt-1">{{ itemToDelete.notes }}</p>
            <p class="text-xs text-muted-foreground mt-2">{{ formatDate(itemToDelete.created_at) }}</p>
          </div>
          <DialogFooter>
            <Button variant="outline" @click="cancelDelete" :disabled="deletingId">
              Batal
            </Button>
            <Button variant="destructive" @click="deleteTrainingData(itemToDelete?.id)" :disabled="deletingId">
              <svg v-if="deletingId" class="w-4 h-4 mr-2 animate-spin" fill="none" stroke="currentColor"
                viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
              </svg>
              {{ deletingId ? 'Menghapus...' : 'Hapus' }}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <!-- Export Dialog -->
      <Dialog v-model:open="showExportDialog">
        <DialogContent class="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>Export Data Training</DialogTitle>
            <DialogDescription>
              Export data training dalam format yang Anda inginkan.
            </DialogDescription>
          </DialogHeader>
          <div class="space-y-4 py-4">
            <div class="space-y-2">
              <Label class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">
                Format
              </Label>
              <Select v-model="exportFormat" :items="[
                { value: 'json', label: 'JSON' },
                { value: 'csv', label: 'CSV' }
              ]" placeholder="Pilih format" />
            </div>
            <div class="space-y-2">
              <Label class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">
                Filter Gesture
              </Label>
              <Select v-model="exportGesture" :items="[{ value: '', label: 'Semua Gesture' }, ...gestureOptions]"
                placeholder="Semua Gesture" />
            </div>
            <div class="space-y-2">
              <Label class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">
                Filter Jumlah Tangan
              </Label>
              <Select v-model="exportHandCount" :items="[
                { value: '', label: 'Semua' },
                { value: '1', label: '1 Tangan' },
                { value: '2', label: '2 Tangan' }
              ]" placeholder="Semua" />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" @click="showExportDialog = false">
              Batal
            </Button>
            <Button @click="exportData">
              Export Data
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  </AppLayout>
</template>
