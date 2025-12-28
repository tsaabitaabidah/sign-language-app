<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import { 
  Dialog, 
  DialogContent, 
  DialogDescription, 
  DialogFooter, 
  DialogHeader, 
  DialogTitle 
} from '@/components/ui/dialog'
import { Select } from '@/components/ui/select'
import AppLayout from '@/layouts/AppLayout.vue'
import { Search, Plus, Edit, Trash2, Eye, Download, Filter } from 'lucide-vue-next'

interface TrainingData {
  id: number
  gesture_id: number
  landmark_data: any
  confidence: number
  notes?: string
  created_at: string
  updated_at: string
  gesture: {
    id: number
    name: string
    label: string
  }
}

interface Gesture {
  id: number
  name: string
  label: string
}

interface Props {
  trainingData: {
    data: TrainingData[]
    links: any[]
    meta: any
  }
  gestures: Gesture[]
  filters: {
    gesture_id?: string
  }
}

const props = defineProps<Props>()

const search = ref('')
const selectedGesture = ref(props.filters.gesture_id || '')
const deleteDialog = ref(false)
const dataToDelete = ref<TrainingData | null>(null)
const selectedItems = ref<number[]>([])
const bulkDeleteDialog = ref(false)

const filteredTrainingData = computed(() => {
  let data = props.trainingData.data
  
  if (search.value) {
    data = data.filter(item => 
      item.gesture.name.toLowerCase().includes(search.value.toLowerCase()) ||
      item.gesture.label.toLowerCase().includes(search.value.toLowerCase()) ||
      (item.notes && item.notes.toLowerCase().includes(search.value.toLowerCase()))
    )
  }
  
  if (selectedGesture.value) {
    data = data.filter(item => item.gesture_id.toString() === selectedGesture.value)
  }
  
  return data
})

const confirmDelete = (data: TrainingData) => {
  dataToDelete.value = data
  deleteDialog.value = true
}

const deleteTrainingData = () => {
  if (dataToDelete.value) {
    router.delete(`/admin/training-data/${dataToDelete.value.id}`, {
      onSuccess: () => {
        deleteDialog.value = false
        dataToDelete.value = null
      }
    })
  }
}

const confirmBulkDelete = () => {
  if (selectedItems.value.length > 0) {
    bulkDeleteDialog.value = true
  }
}

const bulkDelete = () => {
  router.post('/admin/training-data/bulk-delete', {
    ids: selectedItems.value
  }, {
    onSuccess: () => {
      bulkDeleteDialog.value = false
      selectedItems.value = []
    }
  })
}

const toggleSelectAll = () => {
  if (selectedItems.value.length === filteredTrainingData.value.length) {
    selectedItems.value = []
  } else {
    selectedItems.value = filteredTrainingData.value.map(item => item.id)
  }
}

const formatConfidence = (confidence: number) => {
  return `${(confidence * 100).toFixed(1)}%`
}

const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleDateString() + ' ' + new Date(dateString).toLocaleTimeString()
}
</script>

<template>
  <Head title="Training Data Management" />

  <AppLayout>
    <div class="py-6">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
          <div>
            <h1 class="text-2xl font-bold text-gray-900">Training Data Management</h1>
            <p class="text-gray-600">Manage gesture training data and samples</p>
          </div>
          <div class="flex items-center space-x-3">
            <Link href="/admin/training-data/create">
              <Button class="bg-blue-600 hover:bg-blue-700">
                <Plus class="w-4 h-4 mr-2" />
                Add Training Data
              </Button>
            </Link>
          </div>
        </div>

        <!-- Filters -->
        <Card class="mb-6">
          <CardContent class="p-4">
            <div class="flex flex-col sm:flex-row gap-4">
              <div class="relative flex-1">
                <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" />
                <Input
                  v-model="search"
                  placeholder="Search training data..."
                  class="pl-10"
                />
              </div>
              <div class="w-full sm:w-48">
                <Select 
                  v-model="selectedGesture" 
                  :options="[
                    { value: '', label: 'All Gestures' },
                    ...gestures.map(g => ({ value: g.id.toString(), label: g.label }))
                  ]"
                  placeholder="Filter by gesture"
                />
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- Bulk Actions -->
        <div v-if="selectedItems.length > 0" class="mb-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
          <div class="flex items-center justify-between">
            <span class="text-blue-800">
              {{ selectedItems.length }} item(s) selected
            </span>
            <div class="flex items-center space-x-2">
              <Button @click="selectedItems = []" variant="outline" size="sm">
                Clear Selection
              </Button>
              <Button @click="confirmBulkDelete" variant="destructive" size="sm">
                <Trash2 class="w-4 h-4 mr-2" />
                Delete Selected
              </Button>
            </div>
          </div>
        </div>

        <!-- Training Data Table -->
        <Card>
          <CardContent class="p-0">
            <div class="overflow-x-auto">
              <table class="w-full">
                <thead class="bg-gray-50 border-b">
                  <tr>
                    <th class="px-6 py-3 text-left">
                      <input
                        type="checkbox"
                        :checked="selectedItems.length === filteredTrainingData.length && filteredTrainingData.length > 0"
                        @change="toggleSelectAll"
                        class="rounded border-gray-300"
                      />
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Gesture
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Confidence
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Notes
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Created
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Actions
                    </th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  <tr v-for="data in filteredTrainingData" :key="data.id" class="hover:bg-gray-50">
                    <td class="px-6 py-4">
                      <input
                        type="checkbox"
                        :checked="selectedItems.includes(data.id)"
                        @change="selectedItems.includes(data.id) 
                          ? selectedItems = selectedItems.filter(id => id !== data.id)
                          : selectedItems.push(data.id)"
                        class="rounded border-gray-300"
                      />
                    </td>
                    <td class="px-6 py-4">
                      <div>
                        <div class="text-sm font-medium text-gray-900">{{ data.gesture.label }}</div>
                        <div class="text-sm text-gray-500">{{ data.gesture.name }}</div>
                      </div>
                    </td>
                    <td class="px-6 py-4">
                      <Badge :variant="data.confidence > 0.8 ? 'default' : data.confidence > 0.6 ? 'secondary' : 'destructive'">
                        {{ formatConfidence(data.confidence) }}
                      </Badge>
                    </td>
                    <td class="px-6 py-4">
                      <div class="text-sm text-gray-900 truncate max-w-xs">
                        {{ data.notes || '-' }}
                      </div>
                    </td>
                    <td class="px-6 py-4">
                      <div class="text-sm text-gray-500">
                        {{ formatDate(data.created_at) }}
                      </div>
                    </td>
                    <td class="px-6 py-4">
                      <div class="flex items-center space-x-2">
                        <Link :href="`/admin/training-data/${data.id}`">
                          <Button variant="outline" size="sm">
                            <Eye class="w-4 h-4 mr-1" />
                            View
                          </Button>
                        </Link>
                        <Link :href="`/admin/training-data/${data.id}/edit`">
                          <Button variant="outline" size="sm">
                            <Edit class="w-4 h-4 mr-1" />
                            Edit
                          </Button>
                        </Link>
                        <Button
                          variant="outline"
                          size="sm"
                          @click="confirmDelete(data)"
                          class="text-red-600 hover:text-red-700 hover:border-red-300"
                        >
                          <Trash2 class="w-4 h-4 mr-1" />
                          Delete
                        </Button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </CardContent>
        </Card>

        <!-- Empty State -->
        <div v-if="filteredTrainingData.length === 0" class="text-center py-12">
          <div class="text-gray-400 mb-4">
            <svg class="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
          </div>
          <h3 class="text-lg font-medium text-gray-900 mb-2">No training data found</h3>
          <p class="text-gray-600 mb-4">
            {{ search || selectedGesture ? 'Try adjusting your filters' : 'Get started by adding your first training data' }}
          </p>
          <Link v-if="!search && !selectedGesture" href="/admin/training-data/create">
            <Button>
              <Plus class="w-4 h-4 mr-2" />
              Add Training Data
            </Button>
          </Link>
        </div>

        <!-- Pagination -->
        <div v-if="props.trainingData.meta && props.trainingData.meta.last_page > 1" class="mt-6">
          <div class="flex items-center justify-between">
            <div class="text-sm text-gray-700">
              Showing {{ props.trainingData.meta.from }} to {{ props.trainingData.meta.to }} of {{ props.trainingData.meta.total }} results
            </div>
            <div class="flex space-x-2">
              <Button
                v-for="link in props.trainingData.links"
                :key="link.label"
                :variant="link.active ? 'default' : 'outline'"
                size="sm"
                :disabled="!link.url"
                @click="link.url && router.visit(link.url)"
              >
                {{ link.label.replace('&laquo;', '«').replace('&raquo;', '»') }}
              </Button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Delete Confirmation Dialog -->
    <Dialog v-model:open="deleteDialog">
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Delete Training Data</DialogTitle>
          <DialogDescription>
            Are you sure you want to delete this training data? This action cannot be undone.
          </DialogDescription>
        </DialogHeader>
        <DialogFooter>
          <Button variant="outline" @click="deleteDialog = false">
            Cancel
          </Button>
          <Button variant="destructive" @click="deleteTrainingData">
            Delete
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- Bulk Delete Confirmation Dialog -->
    <Dialog v-model:open="bulkDeleteDialog">
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Delete Selected Items</DialogTitle>
          <DialogDescription>
            Are you sure you want to delete {{ selectedItems.length }} training data item(s)? This action cannot be undone.
          </DialogDescription>
        </DialogHeader>
        <DialogFooter>
          <Button variant="outline" @click="bulkDeleteDialog = false">
            Cancel
          </Button>
          <Button variant="destructive" @click="bulkDelete">
            Delete {{ selectedItems.length }} Item(s)
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </AppLayout>
</template>
