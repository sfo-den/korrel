import { merge } from 'lodash'
import { http } from '@/services'
import { userStore } from '@/stores'
import { useLocalStorage } from '@/composables'

export interface UpdateCurrentProfileData {
  current_password: string | null
  name: string
  email: string
  avatar?: string
  new_password?: string
}

export interface CompositeToken {
  'audio-token': string
  'token': string
}

const API_TOKEN_STORAGE_KEY = 'api-token'
const AUDIO_TOKEN_STORAGE_KEY = 'audio-token'

const { get: lsGet, set: lsSet, remove: lsRemove } = useLocalStorage(false) // authentication local storage data aren't namespaced

export const authService = {
  async login (email: string, password: string) {
    const token = await http.post<CompositeToken>('me', { email, password })

    this.setAudioToken(token['audio-token'])
    this.setApiToken(token.token)
  },

  async logout () {
    await http.delete('me')
    this.destroy()
  },

  getProfile: async () => await http.get<User>('me'),

  updateProfile: async (data: UpdateCurrentProfileData) => {
    merge(userStore.current, (await http.put<User>('me', data)))
  },

  getApiToken: () => lsGet(API_TOKEN_STORAGE_KEY),

  hasApiToken () {
    return Boolean(this.getApiToken())
  },

  setApiToken: (token: string) => lsSet(API_TOKEN_STORAGE_KEY, token),

  destroy: () => {
    lsRemove(API_TOKEN_STORAGE_KEY)
    lsRemove(AUDIO_TOKEN_STORAGE_KEY)
  },

  setAudioToken: (token: string) => lsSet(AUDIO_TOKEN_STORAGE_KEY, token),

  getAudioToken: () => {
    // for backward compatibility, we first try to get the audio token, and fall back to the (full-privileged) API token
    return lsGet(AUDIO_TOKEN_STORAGE_KEY) || lsGet(API_TOKEN_STORAGE_KEY)
  },

  requestResetPasswordLink: async (email: string) => await http.post('forgot-password', { email }),

  resetPassword: async (email: string, password: string, token: string) => {
    await http.post('reset-password', { email, password, token })
  }
}
