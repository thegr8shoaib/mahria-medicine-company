import { defineStore } from 'pinia'
import api from '../api/client'

export const useProductStore = defineStore('products', {
  state: () => ({
    list: [],
    cachedAt: null,
    loading: false,
    error: null,
  }),
  getters: {
    byName: (s) => (q, source) => {
      const base = source || s.list
      const term = (q || '').toLowerCase().trim()
      if (!term) return base
      return base.filter(
        (p) =>
          (p.name || '').toLowerCase().includes(term) ||
          (p.sku || '').toLowerCase().includes(term) ||
          (p.barcode || '').toLowerCase().includes(term) ||
          (p.generic_name || '').toLowerCase().includes(term)
      )
    },
  },
  actions: {
    async ensureLoaded(force = false) {
      const fresh = this.cachedAt && Date.now() - this.cachedAt < 5 * 60 * 1000
      if (fresh && !force) return
      this.loading = true
      try {
        const { data } = await api.get('/products/all')
        this.list = data
        this.cachedAt = Date.now()
        this.error = null
      } catch (e) {
        this.error = e
        throw e
      } finally {
        this.loading = false
      }
    },
    invalidate() {
      this.cachedAt = null
    },
  },
})