import { defineStore } from 'pinia'
import { http } from '@/utils/http'
import type { Paginated, UserListResource, UserResource } from '@/types/user'
import type { UserPermissions } from '@/types/permission'

export type UserPayload = Partial<
  Pick<UserResource, 'first_name' | 'last_name' | 'email' | 'mobile' | 'status'> & { role: string | null }
>

export const useUserStore = defineStore('user', {
  state: () => ({
    users: [] as UserListResource[],
    user: null as UserResource | null,
    /** Next page to load. */
    currentPage: 1,
    lastPage: null as number | null,
    perPage: null as number | null,
    total: null as number | null,
    isLoading: false,
    hasMore: true,
  }),

  actions: {
    /** Load the next page of users and append it to the list (infinite scroll). */
    async fetchUsers() {
      // Prevent unnecessary API calls
      if (this.isLoading || !this.hasMore) return

      try {
        this.isLoading = true

        const { data } = await http.get<Paginated<UserListResource>>('users', {
          params: { page: this.currentPage },
        })

        this.users.push(...data.data)

        this.currentPage = data.meta.current_page + 1
        this.lastPage = data.meta.last_page
        this.perPage = data.meta.per_page
        this.total = data.meta.total
        this.hasMore = data.meta.current_page < data.meta.last_page
      } catch (error) {
        console.error('Error fetching users:', error)
      } finally {
        this.isLoading = false
      }
    },

    resetUsers() {
      this.users = []
      this.currentPage = 1
      this.lastPage = null
      this.perPage = null
      this.total = null
      this.hasMore = true
    },

    async fetchUser(id: number | string) {
      try {
        const response = await http.get<{ data: UserResource }>(`users/${id}`)
        this.user = response.data.data
      } catch (error) {
        console.error('Error fetching user:', error)
      }
    },

    async createUser(userData: UserPayload) {
      const response = await http.post<{ data: UserResource }>('users', userData)
      return response.data.data
    },

    async updateUser(id: number | string, userData: UserPayload) {
      const response = await http.put<{ data: UserResource }>(`users/${id}`, userData)
      const user = response.data.data

      this.user = user
      this.users = this.users.map((item) =>
        item.id === user.id
          ? { ...item, name: user.name, email: user.email, mobile: user.mobile, status: user.status }
          : item
      )

      return user
    },

    async deleteUser(id: number) {
      try {
        await http.delete(`users/${id}`)
        this.users = this.users.filter((user) => user.id !== id)
      } catch (error) {
        console.error('Error deleting user:', error)
      }
    },

    async disableTwoFactor({ id }: { id: number }) {
      const response = await http.put<{ data: UserResource }>(`users/${id}/disable-two-factor`)

      return { response, resource: response.data.data }
    },

    // Per-user permissions (super admins only)
    async fetchPermissions(id: number | string) {
      const response = await http.get<{ data: UserPermissions }>(`users/${id}/permissions`)
      return response.data.data
    },

    /** `permissions` is keyed by ability name: what the user should end up with. */
    async updatePermissions(id: number | string, permissions: Record<string, boolean>) {
      const response = await http.put<{ data: UserPermissions }>(`users/${id}/permissions`, { permissions })
      return response.data.data
    },

    async resetPermissions(id: number | string) {
      const response = await http.delete<{ data: UserPermissions }>(`users/${id}/permissions`)
      return response.data.data
    },

    resetUser() {
      this.user = null
    },
  },
})
