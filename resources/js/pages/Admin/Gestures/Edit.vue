<script setup lang="ts">
import { ref } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import AppLayout from '@/layouts/AppLayout.vue'
import { ArrowLeft, Save, Info, CheckCircle, AlertCircle, Edit3 } from 'lucide-vue-next'

interface Gesture {
  id: number
  name: string
  label: string
  description?: string
  is_active: boolean
  created_at: string
  updated_at: string
}

interface Props {
  gesture: Gesture
}

const props = defineProps<Props>()

const form = useForm({
  name: props.gesture.name,
  label: props.gesture.label,
  description: props.gesture.description || '',
  is_active: props.gesture.is_active
})

const submit = () => {
  form.put(`/admin/gestures/${props.gesture.id}`)
}
</script>

<template>
  <Head title="Edit Gesture" />

  <AppLayout>
    <div class="py-6">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="flex items-center justify-between mb-8">
          <div class="flex items-center">
            <Link href="/admin/gestures">
              <Button variant="outline" size="sm" class="mr-4 hover:bg-gray-50">
                <ArrowLeft class="w-4 h-4 mr-2" />
                Back
              </Button>
            </Link>
            <div>
              <h1 class="text-3xl font-bold text-gray-900 tracking-tight">Edit Gesture</h1>
              <p class="text-gray-600 mt-1">Update gesture information for "{{ gesture.label }}"</p>
            </div>
          </div>
        </div>

        <div class="max-w-2xl">
          <!-- Main Form Card -->
          <Card class="shadow-lg border-0 bg-white/90 backdrop-blur-sm">
            <CardHeader class="bg-gradient-to-r from-amber-50 to-orange-50 border-b">
              <CardTitle class="text-xl font-semibold text-gray-900 flex items-center">
                <div class="bg-amber-100 p-2 rounded-lg mr-3">
                  <Edit3 class="w-5 h-5 text-amber-600" />
                </div>
                Update Gesture Details
              </CardTitle>
              <CardDescription class="text-gray-600">
                Modify the gesture information below. Changes will be saved immediately.
              </CardDescription>
            </CardHeader>
            <CardContent class="p-8">
              <form @submit.prevent="submit" class="space-y-8">
                <!-- Name Field -->
                <div class="space-y-2">
                  <Label for="name" class="text-sm font-medium text-gray-700 flex items-center">
                    Name *
                    <span class="ml-2 text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded">Unique identifier</span>
                  </Label>
                  <Input
                    id="name"
                    v-model="form.name"
                    type="text"
                    required
                    :class="{ 'border-red-500 focus:ring-red-500/20': form.errors.name, 'focus:border-blue-500 focus:ring-blue-500/20': !form.errors.name }"
                    placeholder="e.g., hello"
                    class="h-11"
                  />
                  <p v-if="form.errors.name" class="mt-1 text-sm text-red-600 flex items-center">
                    <AlertCircle class="w-4 h-4 mr-1" />
                    {{ form.errors.name }}
                  </p>
                  <p class="mt-2 text-sm text-gray-600 bg-blue-50 p-3 rounded-lg border border-blue-200">
                    <CheckCircle class="w-4 h-4 inline mr-1 text-blue-600" />
                    Unique identifier for the gesture (lowercase, no spaces, alphanumeric only)
                  </p>
                </div>

                <!-- Label Field -->
                <div class="space-y-2">
                  <Label for="label" class="text-sm font-medium text-gray-700 flex items-center">
                    Label *
                    <span class="ml-2 text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded">Display name</span>
                  </Label>
                  <Input
                    id="label"
                    v-model="form.label"
                    type="text"
                    required
                    :class="{ 'border-red-500 focus:ring-red-500/20': form.errors.label, 'focus:border-blue-500 focus:ring-blue-500/20': !form.errors.label }"
                    placeholder="e.g., Hello"
                    class="h-11"
                  />
                  <p v-if="form.errors.label" class="mt-1 text-sm text-red-600 flex items-center">
                    <AlertCircle class="w-4 h-4 mr-1" />
                    {{ form.errors.label }}
                  </p>
                  <p class="mt-2 text-sm text-gray-600 bg-green-50 p-3 rounded-lg border border-green-200">
                    <CheckCircle class="w-4 h-4 inline mr-1 text-green-600" />
                    Display name for the gesture (will be shown to users)
                  </p>
                </div>

                <!-- Description Field -->
                <div class="space-y-2">
                  <Label for="description" class="text-sm font-medium text-gray-700 flex items-center">
                    Description
                    <span class="ml-2 text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded">Optional</span>
                  </Label>
                  <Textarea
                    id="description"
                    v-model="form.description"
                    rows="4"
                    placeholder="Describe how to perform this gesture..."
                    :class="{ 'border-red-500 focus:ring-red-500/20': form.errors.description, 'focus:border-blue-500 focus:ring-blue-500/20': !form.errors.description }"
                    class="resize-none"
                  />
                  <p v-if="form.errors.description" class="mt-1 text-sm text-red-600 flex items-center">
                    <AlertCircle class="w-4 h-4 mr-1" />
                    {{ form.errors.description }}
                  </p>
                  <p class="mt-2 text-sm text-gray-600 bg-purple-50 p-3 rounded-lg border border-purple-200">
                    <Info class="w-4 h-4 inline mr-1 text-purple-600" />
                    Optional description of how to perform this gesture (helpful for training purposes)
                  </p>
                </div>

                <!-- Active Status -->
                <div class="space-y-3">
                  <div class="flex items-center space-x-3 p-4 bg-gray-50 rounded-lg border border-gray-200">
                    <input
                      id="is_active"
                      v-model="form.is_active"
                      type="checkbox"
                      class="w-5 h-5 text-blue-600 border-gray-300 rounded focus:ring-blue-500 focus:ring-2"
                    />
                    <div class="flex-1">
                      <Label for="is_active" class="text-sm font-medium text-gray-900 cursor-pointer">
                        Active Status
                      </Label>
                      <p class="text-sm text-gray-600 mt-1">
                        Enable this gesture to be used for training and detection
                      </p>
                    </div>
                    <div :class="form.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'" 
                         class="px-3 py-1 rounded-full text-xs font-medium">
                      {{ form.is_active ? 'Active' : 'Inactive' }}
                    </div>
                  </div>
                </div>

                <!-- Gesture Info -->
                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                  <h4 class="text-sm font-medium text-gray-900 mb-2">Gesture Information</h4>
                  <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                      <span class="text-gray-600">Created:</span>
                      <span class="ml-2 text-gray-900">{{ new Date(gesture.created_at).toLocaleDateString() }}</span>
                    </div>
                    <div>
                      <span class="text-gray-600">Last Updated:</span>
                      <span class="ml-2 text-gray-900">{{ new Date(gesture.updated_at).toLocaleDateString() }}</span>
                    </div>
                  </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-end space-x-4 pt-8 border-t border-gray-200">
                  <Link href="/admin/gestures">
                    <Button variant="outline" type="button" class="hover:bg-gray-50">
                      Cancel
                    </Button>
                  </Link>
                  <Button 
                    type="submit"
                    :disabled="form.processing"
                    class="bg-gradient-to-r from-amber-600 to-orange-600 hover:from-amber-700 hover:to-orange-700 shadow-lg hover:shadow-xl transition-all duration-200 min-w-[140px]"
                  >
                    <Save class="w-4 h-4 mr-2" />
                    {{ form.processing ? 'Saving...' : 'Save Changes' }}
                  </Button>
                </div>
              </form>
            </CardContent>
          </Card>

          <!-- Help Card -->
          <Card class="mt-6 border-0 shadow-sm bg-gradient-to-r from-amber-50 to-orange-50">
            <CardContent class="p-6">
              <div class="flex items-start space-x-3">
                <div class="bg-amber-100 p-2 rounded-lg">
                  <Info class="w-5 h-5 text-amber-600" />
                </div>
                <div>
                  <h3 class="font-semibold text-gray-900 mb-2">Editing Tips</h3>
                  <ul class="text-sm text-gray-600 space-y-1">
                    <li>• Changes are saved immediately when you click "Save Changes"</li>
                    <li>• Be careful when changing the name as it may affect training data references</li>
                    <li>• Consider the impact on existing training data before deactivating a gesture</li>
                    <li>• Update descriptions to improve training consistency</li>
                  </ul>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
