import { ref, computed } from 'vue'
import { defineStore } from 'pinia'
import api from '@/services/api'

export const useAuthStore = defineStore('auth', () => {
  // -----------------------------
  // State
  // -----------------------------

  const user = ref(null)

  const token = ref(localStorage.getItem('token'))

  const loading = ref(false)

  const errors = ref({})

  // -----------------------------
  // Computed
  // -----------------------------

  const isAuthenticated = computed(() => {
    return !!token.value
  })

  // -----------------------------
  // Register
  // -----------------------------

  const register = async (formData) => {
    loading.value = true
    errors.value = {}

    try {
      const response = await api.post('/register', formData)

      token.value = response.data.token
      user.value = response.data.user

      localStorage.setItem('token', token.value)

      return true
    } catch (error) {
      if (error.response?.status === 422) {
        errors.value = error.response.data.errors
      }

      return false
    } finally {
      loading.value = false
    }
  }

  // -----------------------------
  // Login
  // -----------------------------

  const login = async (credentials) => {
    loading.value = true
    errors.value = {}

    try {
      const response = await api.post('/login', credentials)

      token.value = response.data.token
      user.value = response.data.user

      localStorage.setItem('token', token.value)

      return true
    } catch (error) {
      if (error.response?.status === 422) {
        errors.value = error.response.data.errors
      }

      return false
    } finally {
      loading.value = false
    }
  }

  // -----------------------------
  // Get current authenticated user
  // -----------------------------

  const fetchUser = async () => {
    if (!token.value) {
      return
    }

    try {
      const response = await api.get('/user')

      user.value = response.data
    } catch (error) {
      token.value = null
      user.value = null

      localStorage.removeItem('token')
    }
  }

  // -----------------------------
  // Logout
  // -----------------------------

  const logout = async () => {
    try {
      await api.post('/logout')
    } finally {
      token.value = null
      user.value = null

      localStorage.removeItem('token')
    }
  }

  return {
    user,
    token,
    loading,
    errors,
    isAuthenticated,
    register,
    login,
    fetchUser,
    logout,
  }
})