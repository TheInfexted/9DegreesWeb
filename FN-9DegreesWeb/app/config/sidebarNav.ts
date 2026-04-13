import {
  BanknotesIcon,
  BuildingOffice2Icon,
  Cog6ToothIcon,
  CurrencyDollarIcon,
  ReceiptPercentIcon,
  ShieldCheckIcon,
  Squares2X2Icon,
  TrophyIcon,
  UserGroupIcon,
} from '@heroicons/vue/24/outline'
import type { Component } from 'vue'

/** Outline icons (24px artboard); sized in SidebarItem. */
export const sidebarIconMap = {
  dashboard: Squares2X2Icon,
  sales: CurrencyDollarIcon,
  commissions: ReceiptPercentIcon,
  payouts: BanknotesIcon,
  leaderboard: TrophyIcon,
  ambassadors: UserGroupIcon,
  teams: BuildingOffice2Icon,
  access: ShieldCheckIcon,
  settings: Cog6ToothIcon,
} as const satisfies Record<string, Component>

export type SidebarIconKey = keyof typeof sidebarIconMap

export type SidebarNavItem = {
  to: string
  label: string
  icon: SidebarIconKey
}

export const mainSidebarNav: SidebarNavItem[] = [
  { to: '/', label: 'Dashboard', icon: 'dashboard' },
  { to: '/sales', label: 'Sales', icon: 'sales' },
  { to: '/commissions', label: 'Commissions', icon: 'commissions' },
  { to: '/payouts', label: 'Payouts', icon: 'payouts' },
  { to: '/leaderboard', label: 'Leaderboard', icon: 'leaderboard' },
]

export const mgmtSidebarNav: SidebarNavItem[] = [
  { to: '/ambassadors', label: 'Ambassadors', icon: 'ambassadors' },
  { to: '/teams', label: 'Teams', icon: 'teams' },
  { to: '/access', label: 'Access & Roles', icon: 'access' },
  { to: '/settings', label: 'Settings', icon: 'settings' },
]

export const allSidebarNav: SidebarNavItem[] = [...mainSidebarNav, ...mgmtSidebarNav]
