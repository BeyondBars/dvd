/** One ability on the user permissions page (App\Services\UserPermissions). */
export type PermissionAbility = {
  name: string
  label: string
  description: string
  /** Ability that must also be granted for this one (managing implies viewing). */
  requires: string | null
  /** Granted by the user's role. */
  role: boolean
  /** Per-user override on top of the role, if any. */
  override: 'allow' | 'forbid' | null
  /** What the user ends up with. */
  granted: boolean
}

export type PermissionModule = {
  key: string
  title: string
  description: string
  /** The ability that lets the user open the module (and shows it in the sidebar). */
  view: string
  abilities: PermissionAbility[]
}

/** Shape of GET /users/{id}/permissions. */
export type UserPermissions = {
  role: string | null
  modules: PermissionModule[]
}
