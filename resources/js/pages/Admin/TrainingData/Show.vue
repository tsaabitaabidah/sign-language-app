<script setup lang="ts">
import { ref } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import AppLayout from '@/layouts/AppLayout.vue'
import { ArrowLeft, Edit, Trash2, Calendar, Hand, Target } from 'lucide-vue-next'

interface Gesture {
  id: number
  name: string
  label: string
}

interface TrainingData {
  id: number
  gesture_id: number
  landmark_data: any
  confidence: number
  notes?: string
  created_at: string
  updated_at: string
  gesture: Gesture
}

interface Props {
  trainingData: TrainingData
}

const props = defineProps<Props>()

const formatConfidence = (value: number) => {
  return `${(value * 100).toFixed(1)}%`
}

const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleDateString() + ' ' + new Date(dateString).toLocaleTimeString()
}

const formatLandmarkData = (data: any) => {
  return JSON.stringify(data, null, 2)
}

const deleteTrainingData = () => {
  if (confirm('Are you sure you want to delete this training data? This action cannot be undone.')) {
    window.location.href = `/admin/training-data/${props.trainingData.id}?_method=DELETE`
  }
}
</script>

<template>
  <Head title="Training Data Details" />

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
              <h1 class="text-2xl font-bold text-gray-900">Training Data Details</h1>
              <p class="text-gray-600">View training data information</p>
            </div>
          </div>
          
          <!-- Actions -->
          <div class="flex items-center space-x-3">
            <Link :href="`/admin/training-data/${trainingData.id}/edit`">
              <Button variant="outline">
                <Edit class="w-4 h-4 mr-2" />
                Edit
              </Button>
            </Link>
            <Button variant="destructive" @click="deleteTrainingData">
              <Trash2 class="w-4 h-4 mr-2" />
              Delete
            </Button>
          </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <!-- Main Content -->
          <div class="lg:col-span-2 space-y-6">
            <!-- Gesture Information -->
            <Card>
              <CardHeader>
                <CardTitle class="flex items-center">
                  <Hand class="w-5 h-5 mr-2" />
                  Gesture Information
                </CardTitle>
              </CardHeader>
              <CardContent class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                  <div>
                    <Label class="text-sm font-medium text-gray-700">Gesture Name</Label>
                    <div class="mt-1">
                      <Badge variant="outline" class="text-base px-3 py-1">
                        {{ trainingData.gesture.label }}
                      </Badge>
                    </div>
                  </div>
                  
                  <div>
                    <Label class="text-sm font-medium text-gray-700">Identifier</Label>
                    <div class="mt-1 text-sm text-gray-900">
                      {{ trainingData.gesture.name }}
                    </div>
                  </div>
                </div>
              </CardContent>
            </Card>

            <!-- Landmark Data -->
            <Card>
              <CardHeader>
                <CardTitle>Hand Landmark Data</CardTitle>
                <CardDescription>
                  21 hand landmark points with x, y, z coordinates
                </CardDescription>
              </CardHeader>
              <CardContent>
                <div class="bg-gray-50 p-4 rounded-md">
                  <div class="mb-3 flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-700">
                      {{ trainingData.landmark_data?.length || 0 }} landmark points
                    </span>
                    <Badge :variant="trainingData.confidence > 0.8 ? 'default' : 'secondary'">
                      <Target class="w-3 h-3 mr-1" />
                      {{ formatConfidence(trainingData.confidence) }}
                    </Badge>
                  </div>
                  
                  <div class="bg-white p-3 rounded border text-xs font-mono max-h-96 overflow-y-auto">
                    <pre>{{ formatLandmarkData(trainingData.landmark_data) }}</pre>
                  </div>
                </div>
              </CardContent>
            </Card>

            <!-- Notes -->
            <Card v-if="trainingData.notes">
              <CardHeader>
                <CardTitle>Notes</CardTitle>
              </CardHeader>
              <CardContent>
                <div class="bg-gray-50 p-4 rounded-md">
                  <p class="text-gray-900 whitespace-pre-wrap">{{ trainingData.notes }}</p>
                </div>
              </CardContent>
            </Card>
          </div>

          <!-- Sidebar -->
          <div class="space-y-6">
            <!-- Quick Stats -->
            <Card>
              <CardHeader>
                <CardTitle>Quick Stats</CardTitle>
              </CardHeader>
              <CardContent class="space-y-4">
                <div class="flex items-center justify-between">
                  <span class="text-sm text-gray-600">Confidence</span>
                  <Badge :variant="trainingData.confidence > 0.8 ? 'default' : 'secondary'">
                    {{ formatConfidence(trainingData.confidence) }}
                  </Badge>
                </div>
                
                <div class="flex items-center justify-between">
                  <span class="text-sm text-gray-600">Landmark Points</span>
                  <span class="text-sm font-medium">{{ trainingData.landmark_data?.length || 0 }}</span>
                </div>
                
                <div class="flex items-center justify-between">
                  <span class="text-sm text-gray-600">Data Quality</span>
                  <Badge :variant="trainingData.confidence > 0.9 ? 'default' : trainingData.confidence > 0.7 ? 'secondary' : 'destructive'">
                    {{ trainingData.confidence > 0.9 ? 'Excellent' : trainingData.confidence > 0.7 ? 'Good' : 'Poor' }}
                  </Badge>
                </div>
              </CardContent>
            </Card>

            <!-- Timestamps -->
            <Card>
              <CardHeader>
                <CardTitle class="flex items-center">
                  <Calendar class="w-5 h-5 mr-2" />
                  Timestamps
                </CardTitle>
              </CardHeader>
              <CardContent class="space-y-4">
                <div>
                  <Label class="text-sm font-medium text-gray-700">Created</Label>
                  <div class="mt-1 text-sm text-gray-900">
                    {{ formatDate(trainingData.created_at) }}
                  </div>
                </div>
                
                <div v-if="trainingData.updated_at !== trainingData.created_at">
                  <Label class="text-sm font-medium text-gray-700">Last Updated</Label>
                  <div class="mt-1 text-sm text-gray-900">
                    {{ formatDate(trainingData.updated_at) }}
                  </div>
                </div>
              </CardContent>
            </Card>

            <!-- Actions -->
            <Card>
              <CardHeader>
                <CardTitle>Actions</CardTitle>
              </CardHeader>
              <CardContent class="space-y-3">
                <Link :href="`/admin/training-data/${trainingData.id}/edit`">
                  <Button variant="outline" class="w-full">
                    <Edit class="w-4 h-4 mr-2" />
                    Edit Training Data
                  </Button>
                </Link>
                
                <Link :href="`/admin/training-data?gesture_id=${trainingData.gesture_id}`">
                  <Button variant="outline" class="w-full">
                    <Hand class="w-4 h-4 mr-2" />
                    View Similar Data
                  </Button>
                </Link>
                
                <Button 
                  variant="destructive" 
                  @click="deleteTrainingData"
                  class="w-full"
                >
                  <Trash2 class="w-4 h-4 mr-2" />
                  Delete Data
                </Button>
              </CardContent>
            </Card>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
