<template>
  <div class="login-wrap">
<div class="login-card card">
        <div class="login-brand">
          <img :src="logoUrl" alt="Mehria Medicine Company" class="brand-logo" />
          <h1>Mehria Medicine Company</h1>
        </div>

        <form @submit.prevent="submit" v-if="!loading" novalidate>
          <label class="label" for="email">Email</label>
          <input
            id="email"
            v-model.trim="email"
            type="email"
            class="input"
            placeholder="Enter your email"
            autocomplete="username"
            required
          />
          <label class="label" for="password" style="margin-top: 14px">Password</label>
          <input
            id="password"
            v-model="password"
            type="password"
            class="input"
            placeholder="Enter your password"
            autocomplete="current-password"
            required
          />

          <p v-if="error" class="alert-error" style="margin-top: 14px">{{ error }}</p>

          <button type="submit" class="btn" style="width: 100%; margin-top: 18px" :disabled="submitting">
            <span v-if="submitting" class="spinner" />
            Sign In
          </button>
        </form>

        <div v-if="loading" class="loading">Checking session…</div>
      </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { apiMsg } from '../utils'
import logoUrl from '../assets/logo.png'

const auth = useAuthStore()
const router = useRouter()
const email = ref('')
const password = ref('')
const submitting = ref(false)
const error = ref('')
const loading = ref(false)

async function submit() {
  if (!email.value || !password.value) {
    error.value = 'Please enter email and password.'
    return
  }
  submitting.value = true
  error.value = ''
  try {
    await auth.login(email.value, password.value)
    router.push('/pos')
  } catch (e) {
    error.value = apiMsg(e, 'Login failed. Check your credentials.')
  } finally {
    submitting.value = false
  }
}
</script>

<style scoped>
.login-wrap {
  min-height: 100vh;
  display: grid;
  place-items: center;
  padding: 20px;
  background:
    radial-gradient(900px 500px at 90% -10%, rgba(14, 116, 144, 0.25), transparent 60%),
    radial-gradient(700px 500px at -10% 110%, rgba(5, 150, 105, 0.2), transparent 60%),
    var(--bg);
}
.login-card { width: 100%; max-width: 390px; padding: 32px; }
.login-brand { text-align: center; margin-bottom: 24px; }
.brand-logo {
  width: 96px; height: 96px;
  object-fit: contain;
  margin: 0 auto 14px;
  border-radius: 20px;
  background: #fff;
  box-shadow: var(--shadow-lg);
  padding: 6px;
}
.login-brand h1 { font-size: 21px; }
</style>