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
import AppLayout from '@/layouts/AppLayout.vue'
import { Search, Plus, Edit, Trash2, Shield, ShieldCheck, Eye, Users, Activity } from 'lucide-vue-next'

interface User {
  id: number
  name: string
  email: string
  email_verified_at: string | null
  created_at: string
  updated_at: string
}

interface Props {
  users: {
    data: User[]
    links: any[]
    meta: any
  }
}

const props = defineProps<Props>()

const search = ref('')
const deleteDialog = ref(false)
const userToDelete = ref<User | null>(null)

const filteredUsers = computed(() => {
  if (!search.value) return props.users.data
  
  return props.users.data.filter(user => 
    user.name.toLowerCase().includes(search.value.toLowerCase()) ||
    user.email.toLowerCase().includes(search.value.toLowerCase())
  )
})

const isVerified = (user: User) => {
  return user.email_verified_at !== null
}

const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

const confirmDelete = (user: User) => {
  userToDelete.value = user
  deleteDialog.value = true
}

const deleteUser = () => {
  if (userToDelete.value) {
    router.delete(`/admin/users/${userToDelete.value.id}`, {
      onSuccess: () => {
        deleteDialog.value = false
        userToDelete.value = null
      }
    })
  }
}

const toggleVerification = (user: User) => {
  router.patch(`/admin/users/${user.id}/toggle-verification`, {}, {
    preserveScroll: true
  })
}

const stats = computed(() => {
  const total = props.users.data.length
  const verified = props.users.data.filter(u => isVerified(u)).length
  const unverified = total - verified
  
  return { total, verified, unverified }
})
</script>

<template>
  <Head title="User Management" />

  <AppLayout>
    <div class="py-6">
      <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header with Stats -->
        <div class="mb-8">
          <div class="flex items-center justify-between mb-6">
            <div>
              <h1 class="text-3xl font-bold text-gray-900 tracking-tight">User Management</h1>
              <p class="text-gray-600 mt-1">Manage application users and their permissions</p>
            </div>
            <Link href="/admin/users/create">
              <Button class="bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 shadow-lg hover:shadow-xl transition-all duration-200">
                <Plus class="w-4 h-4 mr-2" />
                Add User
              </Button>
            </Link>
          </div>

          <!-- Stats Cards -->
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <Card class="bg-gradient-to-br from-blue-50 to-blue-100 border-blue-200">
              <CardContent class="p-4">
                <div class="flex items-center justify-between">
                  <div>
                    <p class="text-blue-600 text-sm font-medium">Total Users</p>
                    <p class="text-2xl font-bold text-blue-900">{{ stats.total }}</p>
                  </div>
                  <div class="bg-blue-200 p-3 rounded-full">
                    <Users class="w-6 h-6 text-blue-700" />
                  </div>
                </div>
              </CardContent>
            </Card>

            <Card class="bg-gradient-to-br from-green-50 to-green-100 border-green-200">
              <CardContent class="p-4">
                <div class="flex items-center justify-between">
                  <div>
                    <p class="text-green-600 text-sm font-medium">Verified Users</p>
                    <p class="text-2xl font-bold text-green-900">{{ stats.verified }}</p>
                  </div>
                  <div class="bg-green-200 p-3 rounded-full">
                    <ShieldCheck class="w-6 h-6 text-green-700" />
                  </div>
                </div>
              </CardContent>
            </Card>

            <Card class="bg-gradient-to-br from-orange-50 to-orange-100 border-orange-200">
              <CardContent class="p-4">
                <div class="flex items-center justify-between">
                  <div>
                    <p class="text-orange-600 text-sm font-medium">Unverified Users</p>
                    <p class="text-2xl font-bold text-orange-900">{{ stats.unverified }}</p>
                  </div>
                  <div class="bg-orange-200 p-3 rounded-full">
                    <Shield class="w-6 h-6 text-orange-700" />
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
                placeholder="Search users by name or email..."
                class="pl-12 h-11 bg-white border-gray-200 focus:border-blue-500 focus:ring-blue-500/20"
              />
            </div>
          </CardContent>
        </Card>

        <!-- Users Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <Card v-for="user in filteredUsers" :key="user.id" 
                class="group hover:shadow-xl hover:-translate-y-1 transition-all duration-300 border-0 shadow-md bg-white/80 backdrop-blur-sm">
            <CardHeader class="pb-3">
              <div class="flex items-start justify-between">
                <div class="flex-1">
                  <CardTitle class="text-xl font-semibold text-gray-900 group-hover:text-blue-600 transition-colors">
                    {{ user.name }}
                  </CardTitle>
                  <CardDescription class="text-sm text-gray-500 mt-1">
                    {{ user.email }}
                  </CardDescription>
                </div>
                <div class="flex items-center space-x-2">
                  <Button
                    variant="ghost"
                    size="sm"
                    @click="toggleVerification(user)"
                    :class="isVerified(user) ? 'text-green-600 hover:text-green-700 hover:bg-green-50' : 'text-orange-600 hover:text-orange-700 hover:bg-orange-50'"
                    class="transition-all duration-200"
                  >
                    <ShieldCheck v-if="isVerified(user)" class="w-5 h-5" />
                    <Shield v-else class="w-5 h-5" />
                  </Button>
                </div>
              </div>
            </CardHeader>
            <CardContent class="pt-0">
              <div class="flex items-center justify-between mb-4">
                <Badge :variant="isVerified(user) ? 'default' : 'secondary'" 
                       :class="isVerified(user) ? 'bg-green-100 text-green-800 border-green-200' : 'bg-orange-100 text-orange-800 border-orange-200'">
                  <ShieldCheck v-if="isVerified(user)" class="w-3 h-3 mr-1" />
                  <Shield v-else class="w-3 h-3 mr-1" />
                  {{ isVerified(user) ? 'Verified' : 'Unverified' }}
                </Badge>
                <Badge variant="outline" class="bg-gray-50 text-gray-700 border-gray-200">
                  <Activity class="w-3 h-3 mr-1" />
                  {{ formatDate(user.created_at) }}
                </Badge>
              </div>

              <div class="flex items-center space-x-2">
                <Link :href="`/admin/users/${user.id}`">
                  <Button variant="outline" size="sm" 
                          class="hover:bg-blue-50 hover:border-blue-300 hover:text-blue-700 transition-all duration-200">
                    <Eye class="w-4 h-4 mr-1" />
                    View
                  </Button>
                </Link>
                
                <Link :href="`/admin/users/${user.id}/edit`">
                  <Button variant="outline" size="sm"
                          class="hover:bg-green-50 hover:border-green-300 hover:text-green-700 transition-all duration-200">
                    <Edit class="w-4 h-4 mr-1" />
                    Edit
                  </Button>
                </Link>
                
                <Button
                  variant="outline"
                  size="sm"
                  @click="confirmDelete(user)"
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
        <div v-if="filteredUsers.length === 0" class="text-center py-16">
          <div class="text-gray-300 mb-6">
            <Users class="w-24 h-24 mx-auto" />
          </div>
          <h3 class="text-xl font-semibold text-gray-900 mb-2">No users found</h3>
          <p class="text-gray-600 mb-6 max-w-md mx-auto">
            {{ search ? 'Try adjusting your search terms' : 'Get started by creating your first user account' }}
          </p>
          <Link v-if="!search" href="/admin/users/create">
            <Button class="bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 shadow-lg">
              <Plus class="w-4 h-4 mr-2" />
              Create Your First User
            </Button>
          </Link>
        </div>

        <!-- Pagination -->
        <div v-if="props.users.meta && props.users.meta.last_page > 1" class="mt-8">
          <div class="flex items-center justify-between">
            <div class="text-sm text-gray-700">
              Showing {{ props.users.meta.from }} to {{ props.users.meta.to }} of {{ props.users.meta.total }} results
            </div>
            <div class="flex space-x-2">
              <Button
                v-for="link in props.users.links"
                :key="link.label"
                :variant="link.active ? 'default' : 'outline'"
                size="sm"
                :disabled="!link.url"
                @click="link.url && router.visit(link.url)"
                :class="link.active ? 'bg-blue-600 hover:bg-blue-700' : 'hover:bg-gray-50'"
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
      <DialogContent class="sm:max-w-md">
        <DialogHeader>
          <DialogTitle class="text-lg font-semibold">Delete User</DialogTitle>
          <DialogDescription class="text-gray-600">
            Are you sure you want to delete "<span class="font-medium text-gray-900">{{ userToDelete?.name }}</span>"? 
            This action cannot be undone.
          </DialogDescription>
        </DialogHeader>
        <DialogFooter>
          <Button variant="outline" @click="deleteDialog = false" class="hover:bg-gray-50">
            Cancel
          </Button>
          <Button variant="destructive" @click="deleteUser" class="bg-red-600 hover:bg-red-700">
            Delete User
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </AppLayout>
</template>
