<template>
  <div class="login-wrap">
    <div class="login-card card">
      <div class="login-brand">
        <div class="logo">
          <Pill :size="26" />
        </div>
        <h1>Mehria Medicine Company</h1>
        <p>Pharmacy Point of Sale System</p>
      </div>

      <form @submit.prevent="submit" v-if="!loading" novalidate>
        <label class="label" for="email">Email</label>
        <input
          id="email"
          v-model.trim="email"
          type="email"
          class="input"
          placeholder="admin@pharmacy.test"
          autocomplete="username"
          required
        />
        <label class="label" for="password" style="margin-top: 14px">Password</label>
        <input
          id="password"
          v-model="password"
          type="password"
          class="input"
          placeholder="••••••••"
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

      <div class="demo-hint">
        <strong>Demo accounts</strong>
        <span>admin@pharmacy.test / cashier@pharmacy.test</span>
        <span>Password: <code>password</code></span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { Pill } from 'lucide-vue-next'
import { useAuthStore } from '../stores/auth'
import { apiMsg } from '../utils'

const auth = useAuthStore()
const router = useRouter()
const email = ref('admin@pharmacy.test')
const password = ref('password')
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
.logo {
  width: 58px; height: 58px;
  margin: 0 auto 12px;
  border-radius: 16px;
  background: linear-gradient(135deg, #0e7490, #059669);
  color: #fff;
  display: grid; place-items: center;
  box-shadow: var(--shadow-lg);
}
.login-brand h1 { font-size: 21px; }
.login-brand p { color: var(--muted); font-size: 13px; margin-top: 3px; }
.demo-hint {
  margin-top: 22px;
  padding: 12px;
  background: var(--bg);
  border-radius: 10px;
  font-size: 12px;
  color: var(--muted);
  display: flex;
  flex-direction: column;
  gap: 3px;
  text-align: center;
}
.demo-hint strong { color: var(--text); }
</style>