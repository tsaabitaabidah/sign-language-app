<script setup lang="ts">
import { ref } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Select } from '@/components/ui/select'
import { Badge } from '@/components/ui/badge'
import AppLayout from '@/layouts/AppLayout.vue'
import { ArrowLeft, Save } from 'lucide-vue-next'

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
  gestures: Gesture[]
}

const props = defineProps<Props>()

const form = useForm({
  gesture_id: props.trainingData.gesture_id.toString(),
  landmark_data: props.trainingData.landmark_data,
  confidence_score: props.trainingData.confidence,
  notes: props.trainingData.notes || ''
})

const submit = () => {
  form.put(`/admin/training-data/${props.trainingData.id}`)
}

const formatConfidence = (value: number) => {
  return `${(value * 100).toFixed(1)}%`
}

const formatLandmarkData = (data: any) => {
  return JSON.stringify(data, null, 2)
}
</script>

<template>
  <Head title="Edit Training Data" />

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
              <h1 class="text-2xl font-bold text-gray-900">Edit Training Data</h1>
              <p class="text-gray-600">Update training data information</p>
            </div>
          </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <!-- Main Form -->
          <div class="lg:col-span-2">
            <Card>
              <CardHeader>
                <CardTitle>Training Data Details</CardTitle>
                <CardDescription>
                  Update the training data information below
                </CardDescription>
              </CardHeader>
              <CardContent>
                <form @submit.prevent="submit" class="space-y-6">
                  <!-- Gesture Selection -->
                  <div>
                    <Label for="gesture_id">Gesture</Label>
                    <Select 
                      v-model="form.gesture_id" 
                      :options="gestures.map(g => ({ value: g.id.toString(), label: g.label }))"
                      placeholder="Select a gesture"
                      :class="{ 'border-red-500': form.errors.gesture_id }"
                    />
                    <p v-if="form.errors.gesture_id" class="mt-1 text-sm text-red-600">
                      {{ form.errors.gesture_id }}
                    </p>
                  </div>

                  <!-- Confidence -->
                  <div>
                    <Label for="confidence_score">Confidence</Label>
                    <Input
                      id="confidence_score"
                      v-model="form.confidence_score"
                      type="number"
                      step="0.01"
                      min="0"
                      max="1"
                      required
                      :class="{ 'border-red-500': form.errors.confidence_score }"
                      placeholder="0.85"
                    />
                    <p v-if="form.errors.confidence_score" class="mt-1 text-sm text-red-600">
                      {{ form.errors.confidence_score }}
                    </p>
                    <p class="mt-1 text-sm text-gray-600">
                      Value between 0 and 1 (e.g., 0.85 for 85%)
                    </p>
                  </div>

                  <!-- Notes -->
                  <div>
                    <Label for="notes">Notes</Label>
                    <Textarea
                      id="notes"
                      v-model="form.notes"
                      rows="3"
                      placeholder="Add any notes about this training data..."
                      :class="{ 'border-red-500': form.errors.notes }"
                    />
                    <p v-if="form.errors.notes" class="mt-1 text-sm text-red-600">
                      {{ form.errors.notes }}
                    </p>
                  </div>

                  <!-- Actions -->
                  <div class="flex items-center justify-end space-x-4 pt-6 border-t">
                    <Link href="/admin/training-data">
                      <Button variant="outline" type="button">
                        Cancel
                      </Button>
                    </Link>
                    <Button 
                      type="submit"
                      :disabled="form.processing"
                      class="bg-blue-600 hover:bg-blue-700"
                    >
                      <Save class="w-4 h-4 mr-2" />
                      {{ form.processing ? 'Saving...' : 'Save Changes' }}
                    </Button>
                  </div>
                </form>
              </CardContent>
            </Card>
          </div>

          <!-- Side Panel -->
          <div class="space-y-6">
            <!-- Current Info -->
            <Card>
              <CardHeader>
                <CardTitle>Current Information</CardTitle>
              </CardHeader>
              <CardContent class="space-y-4">
                <div>
                  <Label class="text-sm font-medium text-gray-700">Current Gesture</Label>
                  <div class="mt-1">
                    <Badge variant="outline">
                      {{ props.trainingData.gesture.label }}
                    </Badge>
                  </div>
                </div>
                
                <div>
                  <Label class="text-sm font-medium text-gray-700">Current Confidence</Label>
                  <div class="mt-1">
                    <Badge :variant="props.trainingData.confidence > 0.8 ? 'default' : 'secondary'">
                      {{ formatConfidence(props.trainingData.confidence) }}
                    </Badge>
                  </div>
                </div>

                <div>
                  <Label class="text-sm font-medium text-gray-700">Created</Label>
                  <div class="mt-1 text-sm text-gray-600">
                    {{ new Date(props.trainingData.created_at).toLocaleDateString() }}
                  </div>
                </div>
              </CardContent>
            </Card>

            <!-- Landmark Data Preview -->
            <Card>
              <CardHeader>
                <CardTitle>Landmark Data</CardTitle>
                <CardDescription>
                  Hand landmark coordinates ({{ props.trainingData.landmark_data?.length || 0 }} points)
                </CardDescription>
              </CardHeader>
              <CardContent>
                <div class="bg-gray-50 p-3 rounded-md text-xs font-mono max-h-48 overflow-y-auto">
                  <pre>{{ formatLandmarkData(props.trainingData.landmark_data) }}</pre>
                </div>
              </CardContent>
            </Card>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
