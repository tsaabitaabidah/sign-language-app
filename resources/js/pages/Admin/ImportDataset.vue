<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import AppLogo from '@/components/AppLogo.vue'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Alert, AlertTitle, AlertDescription } from '@/components/ui/alert'
import { Progress } from '@/components/ui/progress'
import { Badge } from '@/components/ui/badge'

const isProcessing = ref(false)
const progress = ref(0)
const logs = ref<string[]>([])
const fileInput = ref<HTMLInputElement | null>(null)
const processedCount = ref(0)
const totalFiles = ref(0) // Total relevant image files
const successCount = ref(0)
const skipCount = ref(0)
const errorCount = ref(0)

const currentFile = ref('')
const currentLabel = ref('')

let hands: any = null
const isModelReady = ref(false)

onMounted(async () => {
  await initializeMediaPipe()
})

const initializeMediaPipe = async () => {
  try {
    const handsModule = await import('@mediapipe/hands')
    
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

    // Prepare a ready signal or warm up
    isModelReady.value = true
    addLog('MediaPipe model initialized successfully.')
  } catch (error) {
    console.error('Error initializing MediaPipe:', error)
    addLog('Error initializing MediaPipe: ' + error)
  }
}

const addLog = (msg: string) => {
  logs.value.unshift(`[${new Date().toLocaleTimeString()}] ${msg}`)
  if (logs.value.length > 100) logs.value.pop()
}

const triggerFileInput = () => {
  fileInput.value?.click()
}

const handleFileSelect = async (event: Event) => {
  const input = event.target as HTMLInputElement
  if (input.files && input.files.length > 0) {
    // Filter for images only
    const validExtensions = ['jpg', 'jpeg', 'png', 'webp']
    const filesToProcess = Array.from(input.files).filter(file => {
       const ext = file.name.split('.').pop()?.toLowerCase()
       return ext && validExtensions.includes(ext)
    })

    if (filesToProcess.length === 0) {
      alert('No image files found in the selected folder.')
      return
    }

    totalFiles.value = filesToProcess.length
    addLog(`Found ${totalFiles.value} image files to process.`)
    
    await processFiles(filesToProcess)
  }
}

const processFiles = async (files: File[]) => {
  isProcessing.value = true
  processedCount.value = 0
  successCount.value = 0
  skipCount.value = 0
  errorCount.value = 0
  progress.value = 0

  for (const file of files) {
    // Extract label from folder name
    // file.webkitRelativePath e.g., "dataset/A/image.jpg" -> Label = "A"
    const pathParts = file.webkitRelativePath.split('/')
    // Usually the immediate parent folder is the label
    // If path is "dataset/A/image.jpg", parts is ["dataset", "A", "image.jpg"] -> length 3. Parent is index 1.
    // If path is just "A/image.jpg", parts is ["A", "image.jpg"] -> Parent is index 0.
    // We assume the standardized structure: Root/Label/Image
    let label = 'Unknown'
    if (pathParts.length >= 2) {
        label = pathParts[pathParts.length - 2]
    }

    currentFile.value = file.name
    currentLabel.value = label

    try {
        const landmarks = await extractLandmarks(file)
        
        if (landmarks) {
            // Upload to backend
            await uploadData(label, landmarks)
            successCount.value++
            addLog(`Success: ${file.name} (Label: ${label})`)
        } else {
            skipCount.value++
            // addLog(`Skipped: ${file.name} (No hands detected)`)
        }
    } catch (err) {
        errorCount.value++
        addLog(`Error processing ${file.name}: ${err}`)
    }

    processedCount.value++
    progress.value = (processedCount.value / totalFiles.value) * 100
    
    // Small delay to prevent UI freeze
    if (processedCount.value % 5 === 0) await new Promise(r => setTimeout(r, 10));
  }

  isProcessing.value = false
  addLog('Import complete!')
  alert(`Import Complete!\nSuccess: ${successCount.value}\nSkipped: ${skipCount.value}\nErrors: ${errorCount.value}`)
}

const extractLandmarks = (file: File): Promise<any> => {
    return new Promise((resolve, reject) => {
        const img = new Image()
        const objectUrl = URL.createObjectURL(file)
        
        img.onload = async () => {
            try {
                // One-shot handler
                const onResult = (results: any) => {
                    if (results.multiHandLandmarks && results.multiHandLandmarks.length > 0) {
                        // Return the first hand or multiple?
                        // Let's return all hands detected
                        resolve(results.multiHandLandmarks)
                    } else {
                        resolve(null)
                    }
                }
                
                // We must hijack the onResults for this specific call
                // Note: In a real concurrent scenario this is bad, but here we process sequentially
                hands.onResults(onResult)
                await hands.send({ image: img })
                
                URL.revokeObjectURL(objectUrl)
            } catch (e) {
                reject(e)
            }
        }
        
        img.onerror = (e) => reject(e)
        img.src = objectUrl
    })
}

const uploadData = async (label: string, landmarks: any[]) => {
    await fetch('/admin/import-data', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        },
        body: JSON.stringify({
            label: label,
            landmarks: landmarks, // This is an array of arrays of points
            confidence: 0.9 // Static confidence for imported data or calculate average if needed
        })
    })
}

</script>

<template>
  <Head title="Import Dataset" />
  <AppLayout>
    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
      <div class="flex items-center gap-2">
         <AppLogo />
         <h1 class="text-2xl font-bold">Import Dataset</h1>
      </div>

      <div class="grid gap-6 md:grid-cols-2">
        <Card>
            <CardHeader>
                <CardTitle>Select Dataset Folder</CardTitle>
                <CardDescription>
                    Select the root folder of your extracted dataset (Kaggle).
                    Structure should be: Root Folder -> Label Folders (A, B, C...) -> Images.
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <div v-if="!isModelReady" class="flex items-center gap-2 text-yellow-600 bg-yellow-50 p-3 rounded">
                    <span>Loading AI Model... please wait.</span>
                </div>

                <div class="flex flex-col gap-4">
                    <!-- Hidden Input -->
                    <input 
                        type="file" 
                        ref="fileInput" 
                        webkitdirectory 
                        directory
                        multiple 
                        class="hidden" 
                        @change="handleFileSelect"
                    />
                    
                    <Button @click="triggerFileInput" :disabled="!isModelReady || isProcessing" size="lg">
                        <span v-if="isProcessing">Processing...</span>
                        <span v-else>Choose Folder & Start Import</span>
                    </Button>
                </div>

                <div v-if="isProcessing || processedCount > 0" class="space-y-2">
                    <div class="flex justify-between text-sm">
                        <span>Progress</span>
                        <span>{{ Math.round(progress) }}%</span>
                    </div>
                    <Progress :model-value="progress" />
                    
                    <div class="grid grid-cols-3 gap-2 mt-2 text-center">
                        <div class="bg-green-100 p-2 rounded text-green-700">
                            <div class="font-bold">{{ successCount }}</div>
                            <div class="text-xs">Success</div>
                        </div>
                        <div class="bg-gray-100 p-2 rounded text-gray-700">
                            <div class="font-bold">{{ skipCount }}</div>
                            <div class="text-xs">Skipped</div>
                        </div>
                        <div class="bg-red-100 p-2 rounded text-red-700">
                            <div class="font-bold">{{ errorCount }}</div>
                            <div class="text-xs">Errors</div>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card class="h-[500px] flex flex-col">
            <CardHeader>
                <CardTitle>Process Log</CardTitle>
            </CardHeader>
            <CardContent class="flex-1 overflow-y-auto bg-gray-950 text-green-400 font-mono text-xs p-4 rounded-b-lg m-4 mt-0">
                <div v-if="logs.length === 0" class="text-gray-500 italic">Waiting for logs...</div>
                <div v-for="(log, i) in logs" :key="i">
                    {{ log }}
                </div>
            </CardContent>
        </Card>
      </div>
    </div>
  </AppLayout>
</template>
