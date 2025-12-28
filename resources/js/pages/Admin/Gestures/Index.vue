<script setup lang="ts">
import { ref, watch, computed } from 'vue'
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
import AppLayout from '@/layouts/AppLayout.vue'
import { Search, Plus, Edit, Trash2, ToggleLeft, ToggleRight, Eye, Database, Activity } from 'lucide-vue-next'
import { debounce } from 'lodash'

interface Gesture {
  id: number
  name: string
  label: string
  description?: string
  is_active: boolean
  training_data_count: number
  created_at: string
  updated_at: string
}

interface PaginationLink {
  url: string | null
  label: string
  active: boolean
}

interface Paginator<T> {
  data: T[]
  current_page: number
  last_page: number
  from: number
  to: number
  total: number
  links: PaginationLink[]
}

interface Stats {
  total: number
  active: number
  totalSamples: number
}

interface Props {
  gestures: Paginator<Gesture>
  stats: Stats
  filters: {
    search?: string
  }
}


const props = defineProps<Props>()

const paginationLinks = computed(() => {
  const links = props.gestures.links
  if (!links || links.length === 0) return []
  
  // If we have very few pages (e.g. up to 5 + 2 prev/next = 7 items), show all
  if (links.length <= 7) return links

  const currentLinkIndex = links.findIndex(l => l.active)
  const windowSize = 3 // User requested specific limit
  
  // Always keep first (Prev) and last (Next)
  const prev = links[0]
  const next = links[links.length - 1]
  
  // Data links are everything in between
  const dataLinks = links.slice(1, -1)
  const activeIndex = dataLinks.findIndex(l => l.active)
  
  // Calculate start index for window
  let start = activeIndex - 1
  if (start < 0) start = 0
  
  // Ensure we show at least windowSize items if possible
  let end = start + windowSize
  if (end > dataLinks.length) {
    end = dataLinks.length
    start = Math.max(0, end - windowSize)
  }
  
  const visibleSubset = dataLinks.slice(start, end)
  
  return [prev, ...visibleSubset, next]
})

const search = ref(props.filters.search || '')
const deleteDialog = ref(false)
const gestureToDelete = ref<Gesture | null>(null)

// Server-side search watcher
watch(search, debounce((value: string) => {
  router.get('/admin/gestures', { search: value }, {
    preserveState: true,
    preserveScroll: true,
    replace: true
  })
}, 300))

const confirmDelete = (gesture: Gesture) => {
  gestureToDelete.value = gesture
  deleteDialog.value = true
}

const deleteGesture = () => {
  if (gestureToDelete.value) {
    router.delete(`/admin/gestures/${gestureToDelete.value.id}`, {
      onSuccess: () => {
        deleteDialog.value = false
        gestureToDelete.value = null
      }
    })
  }
}

const toggleStatus = (gesture: Gesture) => {
  router.patch(`/admin/gestures/${gesture.id}/toggle`, {}, {
    preserveScroll: true
  })
}
</script>

<template>
  <Head title="Gesture Management" />

  <AppLayout>
    <div class="py-6">
      <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header with Stats -->
        <div class="mb-8">
          <div class="flex items-center justify-between mb-6">
            <div>
              <h1 class="text-3xl font-bold text-gray-900 tracking-tight">Gesture Management</h1>
              <p class="text-gray-600 mt-1">Manage sign language gestures and their training data</p>
            </div>
            <Link href="/admin/gestures/create">
              <Button class="bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 shadow-lg hover:shadow-xl transition-all duration-200">
                <Plus class="w-4 h-4 mr-2" />
                Add Gesture
              </Button>
            </Link>
          </div>

          <!-- Stats Cards -->
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <Card class="bg-gradient-to-br from-blue-50 to-blue-100 border-blue-200">
              <CardContent class="p-4">
                <div class="flex items-center justify-between">
                  <div>
                    <p class="text-blue-600 text-sm font-medium">Total Gestures</p>
                    <p class="text-2xl font-bold text-blue-900">{{ props.stats.total }}</p>
                  </div>
                  <div class="bg-blue-200 p-3 rounded-full">
                    <Activity class="w-6 h-6 text-blue-700" />
                  </div>
                </div>
              </CardContent>
            </Card>

            <Card class="bg-gradient-to-br from-green-50 to-green-100 border-green-200">
              <CardContent class="p-4">
                <div class="flex items-center justify-between">
                  <div>
                    <p class="text-green-600 text-sm font-medium">Active Gestures</p>
                    <p class="text-2xl font-bold text-green-900">{{ props.stats.active }}</p>
                  </div>
                  <div class="bg-green-200 p-3 rounded-full">
                    <ToggleRight class="w-6 h-6 text-green-700" />
                  </div>
                </div>
              </CardContent>
            </Card>

            <Card class="bg-gradient-to-br from-purple-50 to-purple-100 border-purple-200">
              <CardContent class="p-4">
                <div class="flex items-center justify-between">
                  <div>
                    <p class="text-purple-600 text-sm font-medium">Training Samples</p>
                    <p class="text-2xl font-bold text-purple-900">{{ props.stats.totalSamples }}</p>
                  </div>
                  <div class="bg-purple-200 p-3 rounded-full">
                    <Database class="w-6 h-6 text-purple-700" />
                  </div>
                </div>
              </CardContent>
            </Card>
          </div>
        </div>

        <!-- Search and Filter Bar -->
        <Card class="mb-6 shadow-sm border-0 bg-gray-50/50">
          <CardContent class="p-6">
            <div class="relative max-w-md">
              <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-5 h-5" />
              <Input
                v-model="search"
                placeholder="Search gestures by name or label..."
                class="pl-12 h-11 bg-white border-gray-200 focus:border-blue-500 focus:ring-blue-500/20"
              />
            </div>
          </CardContent>
        </Card>

        <!-- Gestures Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <Card v-for="gesture in props.gestures.data" :key="gesture.id" 
                class="group hover:shadow-xl hover:-translate-y-1 transition-all duration-300 border-0 shadow-md bg-white/80 backdrop-blur-sm">
            <CardHeader class="pb-3">
              <div class="flex items-start justify-between">
                <div class="flex-1">
                  <CardTitle class="text-xl font-semibold text-gray-900 group-hover:text-blue-600 transition-colors">
                    {{ gesture.label }}
                  </CardTitle>
                  <CardDescription class="text-sm text-gray-500 mt-1 font-mono">
                    {{ gesture.name }}
                  </CardDescription>
                </div>
                <div class="flex items-center space-x-2">
                  <Button
                    variant="ghost"
                    size="sm"
                    @click="toggleStatus(gesture)"
                    :class="gesture.is_active ? 'text-green-600 hover:text-green-700 hover:bg-green-50' : 'text-gray-400 hover:text-gray-600 hover:bg-gray-50'"
                    class="transition-all duration-200"
                  >
                    <ToggleRight v-if="gesture.is_active" class="w-5 h-5" />
                    <ToggleLeft v-else class="w-5 h-5" />
                  </Button>
                </div>
              </div>
            </CardHeader>
            <CardContent class="pt-0">
              <p v-if="gesture.description" class="text-sm text-gray-600 mb-4 line-clamp-2">
                {{ gesture.description }}
              </p>
              
              <div class="flex items-center justify-between mb-4">
                <Badge :variant="gesture.is_active ? 'default' : 'secondary'" 
                       :class="gesture.is_active ? 'bg-green-100 text-green-800 border-green-200' : 'bg-gray-100 text-gray-600 border-gray-200'">
                  {{ gesture.is_active ? 'Active' : 'Inactive' }}
                </Badge>
                <Badge variant="outline" class="bg-blue-50 text-blue-700 border-blue-200">
                  <Database class="w-3 h-3 mr-1" />
                  {{ gesture.training_data_count }} samples
                </Badge>
              </div>

              <div class="flex items-center space-x-2">
                <Link :href="`/admin/gestures/${gesture.id}/edit`">
                  <Button variant="outline" size="sm" 
                          class="hover:bg-blue-50 hover:border-blue-300 hover:text-blue-700 transition-all duration-200">
                    <Edit class="w-4 h-4 mr-1" />
                    Edit
                  </Button>
                </Link>
                
                <Link :href="`/admin/training-data?gesture_id=${gesture.id}`">
                  <Button variant="outline" size="sm"
                          class="hover:bg-purple-50 hover:border-purple-300 hover:text-purple-700 transition-all duration-200">
                    <Eye class="w-4 h-4 mr-1" />
                    View Data
                  </Button>
                </Link>
                
                <Button
                  variant="outline"
                  size="sm"
                  @click="confirmDelete(gesture)"
                  class="text-red-600 hover:text-red-700 hover:border-red-300 hover:bg-red-50 transition-all duration-200"
                >
                  <Trash2 class="w-4 h-4 mr-1" />
                  Delete
                </Button>
              </div>
            </CardContent>
          </Card>
        </div>

        <!-- Empty State -->
        <div v-if="props.gestures.data.length === 0" class="text-center py-16">
          <div class="text-gray-300 mb-6">
            <svg class="w-24 h-24 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
            </svg>
          </div>
          <h3 class="text-xl font-semibold text-gray-900 mb-2">No gestures found</h3>
          <p class="text-gray-600 mb-6 max-w-md mx-auto">
            {{ search ? 'Try adjusting your search terms or filters' : 'Get started by creating your first gesture to begin building your sign language dataset' }}
          </p>
          <Link v-if="!search" href="/admin/gestures/create">
            <Button class="bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 shadow-lg">
              <Plus class="w-4 h-4 mr-2" />
              Create Your First Gesture
            </Button>
          </Link>
        </div>

        <!-- Pagination -->
        <div v-if="props.gestures.last_page > 1" class="mt-8">
          <div class="flex items-center justify-between">
            <div class="text-sm text-gray-700">
              Showing {{ props.gestures.from }} to {{ props.gestures.to }} of {{ props.gestures.total }} results
            </div>
            <div class="flex space-x-2">
              <Button
                v-for="link in paginationLinks"
                :key="link.label"
                :variant="link.active ? 'default' : 'outline'"
                size="sm"
                :disabled="!link.url"
                @click="link.url && router.visit(link.url)"
                :class="link.active ? 'bg-blue-600 hover:bg-blue-700' : 'hover:bg-gray-50'"
               v-html="link.label.replace('&laquo;', '«').replace('&raquo;', '»')"
              />
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Delete Confirmation Dialog -->
    <Dialog v-model:open="deleteDialog">
      <DialogContent class="sm:max-w-md">
        <DialogHeader>
          <DialogTitle class="text-lg font-semibold">Delete Gesture</DialogTitle>
          <DialogDescription class="text-gray-600">
            Are you sure you want to delete "<span class="font-medium text-gray-900">{{ gestureToDelete?.label }}</span>"? 
            This action cannot be undone.
          </DialogDescription>
        </DialogHeader>
        <DialogFooter>
          <Button variant="outline" @click="deleteDialog = false" class="hover:bg-gray-50">
            Cancel
          </Button>
          <Button variant="destructive" @click="deleteGesture" class="bg-red-600 hover:bg-red-700">
            Delete Gesture
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </AppLayout>
</template>
