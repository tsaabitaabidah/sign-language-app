<script setup lang="ts">
import { ref } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import AppLayout from '@/layouts/AppLayout.vue'
import { ArrowLeft, User, Mail, Lock, Shield, ShieldCheck, Calendar } from 'lucide-vue-next'

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

const form = useForm({
  name: props.user.name,
  email: props.user.email,
  password: '',
  password_confirmation: ''
})

const isVerified = props.user.email_verified_at !== null

const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

const submit = () => {
  form.put(`/admin/users/${props.user.id}`, {
    onSuccess: () => {
      // Redirect will be handled by the controller
    }
  })
}
</script>

<template>
  <Head :title="`Edit User - ${user.name}`" />

  <AppLayout>
    <div class="py-6">
      <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8">
          <div class="flex items-center space-x-4 mb-6">
            <Link href="/admin/users">
              <Button variant="outline" size="sm" class="gap-2">
                <ArrowLeft class="w-4 h-4" />
                Back to Users
              </Button>
            </Link>
            <div>
              <h1 class="text-3xl font-bold text-gray-900 tracking-tight">Edit User</h1>
              <p class="text-gray-600 mt-1">Update user information and settings</p>
            </div>
          </div>
        </div>

        <!-- User Info Card -->
        <Card class="mb-6 bg-gradient-to-r from-blue-50 to-indigo-50 border-blue-200">
          <CardContent class="p-6">
            <div class="flex items-center justify-between">
              <div class="flex items-center space-x-4">
                <div class="bg-blue-100 p-3 rounded-full">
                  <User class="w-6 h-6 text-blue-600" />
                </div>
                <div>
                  <h2 class="text-xl font-semibold text-gray-900">{{ user.name }}</h2>
                  <p class="text-gray-600">{{ user.email }}</p>
                </div>
              </div>
              <div class="flex items-center space-x-3">
                <Badge :variant="isVerified ? 'default' : 'secondary'" 
                       :class="isVerified ? 'bg-green-100 text-green-800 border-green-200' : 'bg-orange-100 text-orange-800 border-orange-200'">
                  <ShieldCheck v-if="isVerified" class="w-3 h-3 mr-1" />
                  <Shield v-else class="w-3 h-3 mr-1" />
                  {{ isVerified ? 'Verified' : 'Unverified' }}
                </Badge>
              </div>
            </div>
            <div class="mt-4 pt-4 border-t border-blue-200">
              <div class="flex items-center text-sm text-gray-600">
                <Calendar class="w-4 h-4 mr-2" />
                Member since {{ formatDate(user.created_at) }}
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- Form Card -->
        <Card class="shadow-lg border-0 bg-white/80 backdrop-blur-sm">
          <CardHeader class="bg-gradient-to-r from-blue-50 to-indigo-50 border-b">
            <CardTitle class="flex items-center gap-2">
              <User class="w-5 h-5 text-blue-600" />
              Update User Information
            </CardTitle>
            <CardDescription>
              Modify the user's details below. Leave password fields empty to keep the current password.
            </CardDescription>
          </CardHeader>
          <CardContent class="p-8">
            <form @submit.prevent="submit" class="space-y-6">
              <!-- Name Field -->
              <div class="space-y-2">
                <Label for="name" class="text-sm font-medium text-gray-700 flex items-center gap-2">
                  <User class="w-4 h-4" />
                  Full Name
                </Label>
                <Input
                  id="name"
                  v-model="form.name"
                  type="text"
                  placeholder="Enter user's full name"
                  :class="{ 'border-red-500 focus:border-red-500': form.errors.name }"
                  required
                />
                <p v-if="form.errors.name" class="text-sm text-red-600">
                  {{ form.errors.name }}
                </p>
              </div>

              <!-- Email Field -->
              <div class="space-y-2">
                <Label for="email" class="text-sm font-medium text-gray-700 flex items-center gap-2">
                  <Mail class="w-4 h-4" />
                  Email Address
                </Label>
                <Input
                  id="email"
                  v-model="form.email"
                  type="email"
                  placeholder="user@example.com"
                  :class="{ 'border-red-500 focus:border-red-500': form.errors.email }"
                  required
                />
                <p v-if="form.errors.email" class="text-sm text-red-600">
                  {{ form.errors.email }}
                </p>
              </div>

              <!-- Password Divider -->
              <div class="border-t pt-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4 flex items-center gap-2">
                  <Lock class="w-5 h-5 text-gray-600" />
                  Change Password (Optional)
                </h3>
                <p class="text-sm text-gray-600 mb-4">
                  Leave these fields empty if you don't want to change the user's password.
                </p>
              </div>

              <!-- Password Field -->
              <div class="space-y-2">
                <Label for="password" class="text-sm font-medium text-gray-700 flex items-center gap-2">
                  <Lock class="w-4 h-4" />
                  New Password
                </Label>
                <Input
                  id="password"
                  v-model="form.password"
                  type="password"
                  placeholder="Enter new password (optional)"
                  :class="{ 'border-red-500 focus:border-red-500': form.errors.password }"
                />
                <p v-if="form.errors.password" class="text-sm text-red-600">
                  {{ form.errors.password }}
                </p>
                <p class="text-xs text-gray-500">
                  Password must be at least 8 characters long
                </p>
              </div>

              <!-- Password Confirmation Field -->
              <div class="space-y-2">
                <Label for="password_confirmation" class="text-sm font-medium text-gray-700 flex items-center gap-2">
                  <Shield class="w-4 h-4" />
                  Confirm New Password
                </Label>
                <Input
                  id="password_confirmation"
                  v-model="form.password_confirmation"
                  type="password"
                  placeholder="Confirm the new password"
                  :class="{ 'border-red-500 focus:border-red-500': form.errors.password_confirmation }"
                />
                <p v-if="form.errors.password_confirmation" class="text-sm text-red-600">
                  {{ form.errors.password_confirmation }}
                </p>
              </div>

              <!-- Form Actions -->
              <div class="flex items-center justify-between pt-6 border-t">
                <Link href="/admin/users">
                  <Button variant="outline" type="button" class="gap-2">
                    <ArrowLeft class="w-4 h-4" />
                    Cancel
                  </Button>
                </Link>
                <Button 
                  type="submit" 
                  :disabled="form.processing"
                  class="bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 shadow-lg hover:shadow-xl transition-all duration-200"
                >
                  <User class="w-4 h-4 mr-2" />
                  {{ form.processing ? 'Updating...' : 'Update User' }}
                </Button>
              </div>
            </form>
          </CardContent>
        </Card>

        <!-- Info Card -->
        <Card class="mt-6 bg-amber-50 border-amber-200">
          <CardContent class="p-6">
            <div class="flex items-start gap-3">
              <Shield class="w-5 h-5 text-amber-600 mt-0.5" />
              <div>
                <h3 class="font-semibold text-amber-900 mb-1">Account Verification</h3>
                <p class="text-sm text-amber-700">
                  To toggle this user's verification status, go back to the users list and click the verification icon next to their name.
                </p>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  </AppLayout>
</template>
