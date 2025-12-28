<script setup lang="ts">
import { ref } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import AppLayout from '@/layouts/AppLayout.vue'
import { ArrowLeft, User, Mail, Lock, Shield } from 'lucide-vue-next'

const form = useForm({
  name: '',
  email: '',
  password: '',
  password_confirmation: ''
})

const submit = () => {
  form.post('/admin/users', {
    onSuccess: () => {
      // Redirect will be handled by the controller
    }
  })
}
</script>

<template>
  <Head title="Create User" />

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
              <h1 class="text-3xl font-bold text-gray-900 tracking-tight">Create User</h1>
              <p class="text-gray-600 mt-1">Add a new user to the application</p>
            </div>
          </div>
        </div>

        <!-- Form Card -->
        <Card class="shadow-lg border-0 bg-white/80 backdrop-blur-sm">
          <CardHeader class="bg-gradient-to-r from-blue-50 to-indigo-50 border-b">
            <CardTitle class="flex items-center gap-2">
              <User class="w-5 h-5 text-blue-600" />
              User Information
            </CardTitle>
            <CardDescription>
              Fill in the details below to create a new user account
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

              <!-- Password Field -->
              <div class="space-y-2">
                <Label for="password" class="text-sm font-medium text-gray-700 flex items-center gap-2">
                  <Lock class="w-4 h-4" />
                  Password
                </Label>
                <Input
                  id="password"
                  v-model="form.password"
                  type="password"
                  placeholder="Enter a secure password"
                  :class="{ 'border-red-500 focus:border-red-500': form.errors.password }"
                  required
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
                  Confirm Password
                </Label>
                <Input
                  id="password_confirmation"
                  v-model="form.password_confirmation"
                  type="password"
                  placeholder="Confirm the password"
                  :class="{ 'border-red-500 focus:border-red-500': form.errors.password_confirmation }"
                  required
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
                  {{ form.processing ? 'Creating...' : 'Create User' }}
                </Button>
              </div>
            </form>
          </CardContent>
        </Card>

        <!-- Info Card -->
        <Card class="mt-6 bg-blue-50 border-blue-200">
          <CardContent class="p-6">
            <div class="flex items-start gap-3">
              <Shield class="w-5 h-5 text-blue-600 mt-0.5" />
              <div>
                <h3 class="font-semibold text-blue-900 mb-1">Account Verification</h3>
                <p class="text-sm text-blue-700">
                  New users created through the admin panel will be automatically verified and can immediately access the application.
                </p>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  </AppLayout>
</template>
