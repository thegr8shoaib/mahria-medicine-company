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
              {{ u.name }}
              <span v-if="u.id === auth.user?.id" class="badge badge-blue" style="margin-left: 6px">You</span>
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

    <div v-if="showForm" class="modal-overlay" @click.self="showForm = false">
      <div class="modal card">
        <h3>{{ editing ? 'Edit User' : 'Create User' }}</h3>
        <p v-if="error" class="alert-error">{{ error }}</p>
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
import { onMounted, ref } from 'vue'
import { Pencil, Plus, Trash2 } from 'lucide-vue-next'
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

const permissionOptions = [
  { key: 'sales', label: 'Sales' },
  { key: 'inventory', label: 'Inventory' },
  { key: 'purchases', label: 'Purchases' },
  { key: 'customers', label: 'Customers' },
]

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
  form.value = u
    ? { name: u.name, email: u.email, password: '', role: u.role, permissions: [...(u.permissions || [])] }
    : { name: '', email: '', password: '', role: 'cashier', permissions: ['sales', 'inventory', 'purchases', 'customers'] }
  error.value = ''
  showForm.value = true
}

function onRoleChange() {
  if (form.value.role === 'admin') form.value.permissions = []
}

async function submit() {
  saving.value = true
  error.value = ''
  const payload = { ...form.value }
  if (editing.value && !payload.password) delete payload.password
  if (payload.role === 'admin') delete payload.permissions
  try {
    if (editing.value) await api.put(`/users/${editing.value.id}`, payload)
    else await api.post('/users', payload)
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