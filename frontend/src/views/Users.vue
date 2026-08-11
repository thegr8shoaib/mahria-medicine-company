<template>
  <div>
    <div class="page-header">
      <h1>Users</h1>
      <button class="btn" @click="openForm()"><Plus /> Create User</button>
    </div>

    <div v-if="loading" class="card loading"><span class="spinner spinner-dark" /></div>
    <div v-else class="card" style="padding: 0; overflow: auto">
      <table class="table">
        <thead>
          <tr><th>Name</th><th>Email</th><th>Role</th><th>Permissions</th><th>Created</th><th style="text-align:right">Actions</th></tr>
        </thead>
        <tbody>
          <tr v-for="u in users" :key="u.id">
            <td style="font-weight: 600">
              <span class="u-cell">
                <img v-if="u.avatar" :src="`/api/avatar/${encodeURIComponent(u.avatar)}`" alt="" class="u-img" />
                <span v-else class="u-img u-ph">?</span>
                <span>
                  {{ u.name }}
                  <span v-if="u.id === auth.user?.id" class="badge badge-blue" style="margin-left: 6px">You</span>
                </span>
              </span>
            </td>
            <td>{{ u.email }}</td>
            <td>
              <span class="badge" :class="u.role === 'admin' ? 'badge-blue' : 'badge-gray'">{{ u.role }}</span>
            </td>
            <td>
              <span v-if="u.role === 'admin'" class="muted">All</span>
              <span v-else-if="!(u.permissions || []).length" class="muted">None</span>
              <span v-else class="perm-list">
                <template v-for="p in permissionOptions" :key="p.key">
                  <span v-if="(u.permissions || []).includes(p.key)" class="badge badge-green">{{ p.label }}</span>
                </template>
              </span>
            </td>
            <td class="muted">{{ formatDate(u.created_at) }}</td>
            <td style="text-align:right; white-space: nowrap">
              <button class="btn btn-sm btn-secondary" @click="openForm(u)"><Pencil /></button>
              <button
                class="btn btn-sm btn-secondary"
                style="color: var(--danger)"
                :disabled="u.id === auth.user?.id"
                @click="remove(u)"
              >
                <Trash2 />
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="showForm" class="modal-overlay">
      <div class="modal card">
        <h3>{{ editing ? 'Edit User' : 'Create User' }}</h3>
        <p v-if="error" class="alert-error">{{ error }}</p>

        <div v-if="editing" class="form-avatar">
          <img v-if="formAvatarSrc" :src="formAvatarSrc" alt="" class="form-avatar-img" />
          <div v-else class="form-avatar-img form-avatar-ph">{{ formNameShort }}</div>
          <div class="form-avatar-actions">
            <button class="btn btn-sm btn-secondary" type="button" @click="pickAvatar"><Upload class="icon" /> Photo</button>
            <button v-if="form.avatar || avatarFile" class="btn btn-sm btn-secondary" type="button" @click="clearAvatar"><Trash2 class="icon" /></button>
            <input ref="fileInput" type="file" accept="image/*" class="hidden" @change="onFile" />
          </div>
        </div>

        <label class="label">Full name *</label>
        <input v-model="form.name" class="input" required />
        <label class="label" style="margin-top: 10px">Email *</label>
        <input v-model.trim="form.email" type="email" class="input" required />
        <label class="label" style="margin-top: 10px">Password {{ editing ? '(leave blank to keep current)' : '*' }}</label>
        <input v-model="form.password" type="password" class="input" autocomplete="new-password" :required="!editing" minlength="6" />
        <label class="label" style="margin-top: 10px">Role</label>
        <select v-model="form.role" class="select" @change="onRoleChange">
          <option value="cashier">Cashier</option>
          <option value="admin">Admin</option>
        </select>

        <div v-if="form.role === 'cashier'" class="perms-section">
          <label class="label" style="margin-top: 14px">Give Rights</label>
          <div class="perm-checks">
            <label v-for="p in permissionOptions" :key="p.key" class="perm-check">
              <input type="checkbox" v-model="form.permissions" :value="p.key" />
              <span>{{ p.label }}</span>
            </label>
          </div>
        </div>
        <p v-else class="muted" style="font-size: 12px; margin-top: 10px">Admins have access to everything.</p>

        <div class="modal-actions">
          <button class="btn btn-secondary" @click="showForm = false">Cancel</button>
          <button class="btn" :disabled="saving" @click="submit">
            <span v-if="saving" class="spinner" /> {{ editing ? 'Save' : 'Create User' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { Pencil, Plus, Trash2, Upload } from 'lucide-vue-next'
import api from '../api/client'
import { useAuthStore } from '../stores/auth'
import { apiMsg, formatDate } from '../utils'

const auth = useAuthStore()
const users = ref([])
const loading = ref(true)

const showForm = ref(false)
const editing = ref(null)
const saving = ref(false)
const error = ref('')
const form = ref({ name: '', email: '', password: '', role: 'cashier', permissions: ['sales', 'inventory', 'purchases', 'customers'] })
const avatarFile = ref(null)
const previewUrl = ref('')
const fileInput = ref(null)

const permissionOptions = [
  { key: 'sales', label: 'Sales' },
  { key: 'inventory', label: 'Inventory' },
  { key: 'purchases', label: 'Purchases' },
  { key: 'customers', label: 'Customers' },
]

const formAvatarSrc = computed(() => {
  if (avatarFile.value) return previewUrl.value
  return form.value.avatar ? `/api/avatar/${encodeURIComponent(form.value.avatar)}` : ''
})
const formNameShort = computed(() =>
  (form.value.name || '?').split(' ').map((p) => p[0]).slice(0, 2).join('').toUpperCase() || '?'
)

async function load() {
  loading.value = true
  try {
    const res = await api.get('/users')
    users.value = res.data
  } catch (e) {
    alert(apiMsg(e))
  } finally {
    loading.value = false
  }
}

function openForm(u = null) {
  editing.value = u
  avatarFile.value = null
  previewUrl.value = ''
  form.value = u
    ? { name: u.name, email: u.email, password: '', role: u.role, permissions: [...(u.permissions || [])], avatar: u.avatar || null }
    : { name: '', email: '', password: '', role: 'cashier', permissions: ['sales', 'inventory', 'purchases', 'customers'], avatar: null }
  error.value = ''
  showForm.value = true
}

function pickAvatar() {
  fileInput.value?.click()
}

function onFile(e) {
  const f = e.target.files?.[0]
  if (!f) return
  avatarFile.value = f
  const reader = new FileReader()
  reader.onload = () => {
    previewUrl.value = reader.result
  }
  reader.readAsDataURL(f)
}

function clearAvatar() {
  avatarFile.value = null
  previewUrl.value = ''
  form.value.avatar = null
}

function onRoleChange() {
  if (form.value.role === 'admin') form.value.permissions = []
}

async function submit() {
  saving.value = true
  error.value = ''
  const base = { name: form.value.name, email: form.value.email, role: form.value.role }
  if (form.value.password) base.password = form.value.password
  if (form.value.role === 'cashier') base.permissions = form.value.permissions

  const avatarChanged = !!avatarFile.value || (form.value.avatar === null && !!editing.value?.avatar)
  try {
    if (avatarChanged) {
      const fd = new FormData()
      for (const [k, v] of Object.entries(base)) {
        if (Array.isArray(v)) v.forEach((x) => fd.append(k + '[]', x))
        else fd.append(k, v)
      }
      if (avatarFile.value) fd.append('avatar', avatarFile.value)
      else fd.append('avatar', '')
      if (editing.value) await api.put(`/users/${editing.value.id}`, fd)
      else await api.post('/users', fd)
    } else if (editing.value) {
      await api.put(`/users/${editing.value.id}`, base)
    } else {
      await api.post('/users', base)
    }
    showForm.value = false
    load()
  } catch (e) {
    error.value = apiMsg(e)
  } finally {
    saving.value = false
  }
}

async function remove(u) {
  if (!confirm(`Delete user "${u.name}"?`)) return
  try {
    await api.delete(`/users/${u.id}`)
    load()
  } catch (e) {
    alert(apiMsg(e))
  }
}

onMounted(load)
</script>

<style scoped>
.muted { color: var(--muted); }
.u-cell { display: inline-flex; align-items: center; gap: 8px; }
.u-img { width: 30px; height: 30px; border-radius: 8px; object-fit: cover; flex-shrink: 0; }
.u-ph { background: linear-gradient(135deg, #0e7490, #059669); color: #fff; font-weight: 700; display: inline-grid; place-items: center; }
.hidden { display: none; }
.form-avatar { display: flex; align-items: center; gap: 12px; margin-top: 12px; }
.form-avatar-img { width: 64px; height: 64px; border-radius: 14px; object-fit: cover; flex-shrink: 0; }
.form-avatar-ph { background: linear-gradient(135deg, #0e7490, #059669); color: #fff; font-weight: 700; font-size: 18px; display: grid; place-items: center; }
.form-avatar-actions { display: flex; gap: 8px; }
.form-avatar-actions .icon { width: 14px; height: 14px; }
.modal-overlay {
  position: fixed; inset: 0; background: rgba(15, 23, 42, 0.55);
  display: grid; place-items: center; z-index: 60; padding: 20px;
}
.modal { width: 100%; max-width: 440px; }
.modal-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 16px; }
.perm-list { display: inline-flex; flex-wrap: wrap; gap: 4px; }
.perm-checks { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 8px; }
.perm-check { display: inline-flex; align-items: center; gap: 6px; font-size: 13.5px; cursor: pointer; }
.perm-check input { accent-color: var(--primary); width: 16px; height: 16px; }
</style>