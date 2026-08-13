<template>
  <div>
    <div class="page-header">
      <h1>My Profile</h1>
    </div>

    <div class="grid">
      <div class="card">
        <h3>Profile</h3>
        <p v-if="error" class="alert-error">{{ error }}</p>
        <p v-if="okmsg" class="alert-success">{{ okmsg }}</p>

        <div class="avatar-row">
          <img v-if="avatarSrc" :src="avatarSrc" alt="avatar" class="avatar-img" />
          <div v-else class="avatar-img avatar-placeholder">{{ initials }}</div>
          <div class="avatar-actions">
            <button class="btn btn-sm btn-secondary" @click="pickAvatar" type="button">
              <Upload class="icon" /> Upload photo
            </button>
            <button v-if="avatarSrc" class="btn btn-sm btn-secondary" @click="removeAvatar" type="button">
              <Trash2 class="icon" /> Remove
            </button>
            <input ref="fileInput" type="file" accept="image/*" class="hidden" @change="onFile" />
            <span v-if="avatarName" class="muted" style="font-size: 12px">{{ avatarName }}</span>
          </div>
        </div>

        <label class="label" style="margin-top: 14px">Full name *</label>
        <input v-model="form.name" class="input" />
        <label class="label" style="margin-top: 10px">Email *</label>
        <input v-model.trim="form.email" type="email" class="input" />

        <div class="modal-actions">
          <button class="btn" :disabled="saving" @click="saveProfile">
            <span v-if="saving" class="spinner" /> Save Profile
          </button>
        </div>
      </div>

      <div class="card">
        <h3>Change Password</h3>
        <p v-if="pwError" class="alert-error">{{ pwError }}</p>
        <p v-if="pwMsg" class="alert-success">{{ pwMsg }}</p>
        <label class="label">Current password *</label>
        <input v-model="pw.current_password" type="password" class="input" autocomplete="current-password" />
        <label class="label" style="margin-top: 10px">New password *</label>
        <input v-model="pw.password" type="password" class="input" autocomplete="new-password" minlength="6" />
        <label class="label" style="margin-top: 10px">Confirm new password *</label>
        <input v-model="pw.password_confirmation" type="password" class="input" autocomplete="new-password" minlength="6" />
        <div class="modal-actions">
          <button class="btn" :disabled="savingPw" @click="savePassword">
            <span v-if="savingPw" class="spinner" /> Update Password
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { Trash2, Upload } from 'lucide-vue-next'
import api from '../api/client'
import { useAuthStore } from '../stores/auth'
import { apiMsg } from '../utils'

const auth = useAuthStore()

const form = ref({ name: '', email: '' })
const avatar = ref(null)
const avatarFile = ref(null)
const avatarName = ref('')
const fileInput = ref(null)

const pw = ref({ current_password: '', password: '', password_confirmation: '' })

const saving = ref(false)
const savingPw = ref(false)
const error = ref('')
const okmsg = ref('')
const pwError = ref('')
const pwMsg = ref('')

const avatarSrc = computed(() => (avatar.value ? `/api/avatar/${encodeURIComponent(avatar.value)}` : null))
const initials = computed(() =>
  (auth.user?.name || '')
    .split(' ')
    .map((p) => p[0])
    .slice(0, 2)
    .join('')
    .toUpperCase()
)

onMounted(() => {
  form.value = { name: auth.user?.name || '', email: auth.user?.email || '' }
  avatar.value = auth.user?.avatar || null
})

function pickAvatar() {
  fileInput.value?.click()
}

function onFile(e) {
  const f = e.target.files?.[0]
  if (!f) return
  avatarFile.value = f
  avatarName.value = f.name
  avatar.value = `preview:${Date.now()}`
  const reader = new FileReader()
  reader.onload = () => {
    const img = document.querySelector('.avatar-img')
    if (img) img.src = reader.result
  }
  reader.readAsDataURL(f)
}

function removeAvatar() {
  avatarFile.value = null
  avatarName.value = ''
  avatar.value = null
}

async function saveProfile() {
  saving.value = true
  error.value = ''
  okmsg.value = ''
  const fd = new FormData()
  fd.append('name', form.value.name)
  fd.append('email', form.value.email)
  if (avatarFile.value) fd.append('avatar', avatarFile.value)
  else if (!avatar.value) fd.append('avatar', '')
  try {
    const { data } = await api.put('/me', fd)
    auth.user = data.user
    localStorage.setItem('user', JSON.stringify(data.user))
    avatar.value = data.user.avatar || null
    okmsg.value = data.message
  } catch (e) {
    error.value = apiMsg(e)
  } finally {
    saving.value = false
  }
}

async function savePassword() {
  if (!pw.value.current_password || !pw.value.password) return
  pwError.value = ''
  pwMsg.value = ''
  savingPw.value = true
  try {
    const { data } = await api.put('/me', {
      current_password: pw.value.current_password,
      password: pw.value.password,
      password_confirmation: pw.value.password_confirmation,
    })
    pwMsg.value = data.message
    pw.value = { current_password: '', password: '', password_confirmation: '' }
  } catch (e) {
    pwError.value = apiMsg(e)
  } finally {
    savingPw.value = false
  }
}
</script>

<style scoped>
.grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 16px; align-items: start; }
.hidden { display: none; }
.avatar-row { display: flex; align-items: center; gap: 14px; margin-top: 8px; }
.avatar-img {
  width: 76px; height: 76px; border-radius: 16px; object-fit: cover;
  background: linear-gradient(135deg, #0e7490, #059669);
  color: #fff; font-weight: 700; font-size: 22px;
  display: grid; place-items: center;
}
.avatar-actions { display: flex; flex-direction: column; align-items: flex-start; gap: 8px; }
.avatar-actions .icon { width: 14px; height: 14px; }
</style>