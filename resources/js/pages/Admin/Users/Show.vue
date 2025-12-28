<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { 
  Dialog, 
  DialogContent, 
  DialogDescription, 
  DialogFooter, 
  DialogHeader, 
  DialogTitle 
} from '@/components/ui/dialog'
import AppLayout from '@/layouts/AppLayout.vue'
import { ArrowLeft, User, Mail, Shield, ShieldCheck, Calendar, Edit, Trash2, Activity } from 'lucide-vue-next'

interface User {
  id: number
  name: string
  email: string
  email_verified_at: string | null
  created_at: string
  updated_at: string
}

interface Props {
  user: User
}

const props = defineProps<Props>()

const deleteDialog = ref(false)

const isVerified = computed(() => props.user.email_verified_at !== null)

const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

const confirmDelete = () => {
  deleteDialog.value = true
}

const deleteUser = () => {
  router.delete(`/admin/users/${props.user.id}`, {
    onSuccess: () => {
      deleteDialog.value = false
    }
  })
}

const toggleVerification = () => {
  router.patch(`/admin/users/${props.user.id}/toggle-verification`, {}, {
    preserveScroll: true
  })
}
</script>

<template>
  <Head :title="`User Details - ${user.name}`" />

  <AppLayout>
    <div class="py-6">
      <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8">
          <div class="flex items-center justify-between mb-6">
            <div class="flex items-center space-x-4">
              <Link href="/admin/users">
                <Button variant="outline" size="sm" class="gap-2">
                  <ArrowLeft class="w-4 h-4" />
                  Back to Users
                </Button>
              </Link>
              <div>
                <h1 class="text-3xl font-bold text-gray-900 tracking-tight">User Details</h1>
                <p class="text-gray-600 mt-1">View and manage user information</p>
              </div>
            </div>
            <div class="flex items-center space-x-2">
              <Link :href="`/admin/users/${user.id}/edit`">
                <Button variant="outline" size="sm" class="gap-2">
                  <Edit class="w-4 h-4" />
                  Edit
                </Button>
              </Link>
              <Button
                variant="outline"
                size="sm"
                @click="confirmDelete"
                class="text-red-600 hover:text-red-700 hover:border-red-300 hover:bg-red-50 gap-2"
              >
                <Trash2 class="w-4 h-4" />
                Delete
              </Button>
            </div>
          </div>
        </div>

        <!-- User Profile Card -->
        <Card class="mb-6 shadow-lg border-0 bg-gradient-to-br from-blue-50 to-indigo-50">
          <CardContent class="p-8">
            <div class="flex items-center justify-between mb-6">
              <div class="flex items-center space-x-6">
                <div class="bg-blue-100 p-4 rounded-full">
                  <User class="w-8 h-8 text-blue-600" />
                </div>
                <div>
                  <h2 class="text-2xl font-bold text-gray-900">{{ user.name }}</h2>
                  <p class="text-gray-600 text-lg">{{ user.email }}</p>
                </div>
              </div>
              <div class="flex items-center space-x-3">
                <Button
                  variant="outline"
                  size="sm"
                  @click="toggleVerification"
                  :class="isVerified ? 'text-green-600 hover:text-green-700 hover:bg-green-50' : 'text-orange-600 hover:text-orange-700 hover:bg-orange-50'"
                  class="transition-all duration-200"
                >
                  <ShieldCheck v-if="isVerified" class="w-5 h-5" />
                  <Shield v-else class="w-5 h-5" />
                </Button>
                <Badge :variant="isVerified ? 'default' : 'secondary'" 
                       :class="isVerified ? 'bg-green-100 text-green-800 border-green-200' : 'bg-orange-100 text-orange-800 border-orange-200'">
                  <ShieldCheck v-if="isVerified" class="w-3 h-3 mr-1" />
                  <Shield v-else class="w-3 h-3 mr-1" />
                  {{ isVerified ? 'Verified' : 'Unverified' }}
                </Badge>
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <!-- Account Information -->
              <div class="space-y-4">
                <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                  <User class="w-5 h-5 text-gray-600" />
                  Account Information
                </h3>
                <div class="space-y-3">
                  <div class="flex justify-between items-center py-2 border-b border-gray-200">
                    <span class="text-sm font-medium text-gray-600">User ID</span>
                    <span class="text-sm text-gray-900 font-mono">#{{ user.id }}</span>
                  </div>
                  <div class="flex justify-between items-center py-2 border-b border-gray-200">
                    <span class="text-sm font-medium text-gray-600">Name</span>
                    <span class="text-sm text-gray-900">{{ user.name }}</span>
                  </div>
                  <div class="flex justify-between items-center py-2 border-b border-gray-200">
                    <span class="text-sm font-medium text-gray-600">Email</span>
                    <span class="text-sm text-gray-900">{{ user.email }}</span>
                  </div>
                </div>
              </div>

              <!-- Account Status -->
              <div class="space-y-4">
                <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                  <Activity class="w-5 h-5 text-gray-600" />
                  Account Status
                </h3>
                <div class="space-y-3">
                  <div class="flex justify-between items-center py-2 border-b border-gray-200">
                    <span class="text-sm font-medium text-gray-600">Verification Status</span>
                    <Badge :variant="isVerified ? 'default' : 'secondary'" 
                           :class="isVerified ? 'bg-green-100 text-green-800 border-green-200' : 'bg-orange-100 text-orange-800 border-orange-200'">
                      {{ isVerified ? 'Verified' : 'Unverified' }}
                    </Badge>
                  </div>
                  <div class="flex justify-between items-center py-2 border-b border-gray-200">
                    <span class="text-sm font-medium text-gray-600">Member Since</span>
                    <span class="text-sm text-gray-900">{{ formatDate(user.created_at) }}</span>
                  </div>
                  <div class="flex justify-between items-center py-2 border-b border-gray-200">
                    <span class="text-sm font-medium text-gray-600">Last Updated</span>
                    <span class="text-sm text-gray-900">{{ formatDate(user.updated_at) }}</span>
                  </div>
                </div>
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- Quick Actions -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
          <Card class="border-0 shadow-md bg-white/80 backdrop-blur-sm">
            <CardHeader class="pb-3">
              <CardTitle class="text-lg flex items-center gap-2">
                <Edit class="w-5 h-5 text-blue-600" />
                Quick Actions
              </CardTitle>
            </CardHeader>
            <CardContent class="space-y-3">
              <Link :href="`/admin/users/${user.id}/edit`">
                <Button variant="outline" class="w-full justify-start gap-2">
                  <Edit class="w-4 h-4" />
                  Edit User Information
                </Button>
              </Link>
              <Button 
                variant="outline" 
                class="w-full justify-start gap-2"
                @click="toggleVerification"
              >
                <ShieldCheck v-if="!isVerified" class="w-4 h-4" />
                <Shield v-else class="w-4 h-4" />
                {{ isVerified ? 'Unverify Account' : 'Verify Account' }}
              </Button>
            </CardContent>
          </Card>

          <Card class="border-0 shadow-md bg-white/80 backdrop-blur-sm">
            <CardHeader class="pb-3">
              <CardTitle class="text-lg flex items-center gap-2">
                <Mail class="w-5 h-5 text-green-600" />
                Contact Information
              </CardTitle>
            </CardHeader>
            <CardContent class="space-y-3">
              <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                <Mail class="w-4 h-4 text-gray-600" />
                <div>
                  <p class="text-sm font-medium text-gray-900">Email Address</p>
                  <p class="text-sm text-gray-600">{{ user.email }}</p>
                </div>
              </div>
              <Button variant="outline" class="w-full justify-start gap-2">
                <Mail class="w-4 h-4" />
                Send Email
              </Button>
            </CardContent>
          </Card>
        </div>

        <!-- Info Card -->
        <Card class="bg-blue-50 border-blue-200">
          <CardContent class="p-6">
            <div class="flex items-start gap-3">
              <Shield class="w-5 h-5 text-blue-600 mt-0.5" />
              <div>
                <h3 class="font-semibold text-blue-900 mb-1">Account Management</h3>
                <p class="text-sm text-blue-700">
                  You can edit user information, toggle verification status, or delete this account from this page. 
                  All actions are logged for security purposes.
                </p>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>

    <!-- Delete Confirmation Dialog -->
    <Dialog v-model:open="deleteDialog">
      <DialogContent class="sm:max-w-md">
        <DialogHeader>
          <DialogTitle class="text-lg font-semibold">Delete User</DialogTitle>
          <DialogDescription class="text-gray-600">
            Are you sure you want to delete "<span class="font-medium text-gray-900">{{ user.name }}</span>"? 
            This action cannot be undone and will permanently remove the user's account.
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
